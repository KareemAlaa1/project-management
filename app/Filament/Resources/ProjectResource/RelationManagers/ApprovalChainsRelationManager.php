<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ApprovalChain;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ApprovalChainsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvalChains';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Approval Chains';

    protected static function getNavigationLabel(): string
    {
        return __('Approval Chains');
    }

    public static function canViewForRecord($ownerRecord): bool
    {
        // Only show if user can update the project (owner or admin)
        $userId = auth()->id(); // ← Add this line

        return $ownerRecord->owner_id === auth()->id()
            || $ownerRecord->approvalChains()->whereHas('chainUsers', fn($q) => $q->where('user_id', $userId))->exists()
            || $ownerRecord->users()->where('user_id', auth()->id())
                ->where('role', config('system.projects.affectations.roles.can_manage'))
                ->exists();
    }

    public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Select::make('users')
            ->label(__('Approval Chain Users'))
            ->options(function (RelationManager $livewire) {
                $project = $livewire->getOwnerRecord();
            
                // Get all user IDs assigned to the project
                $projectUserIds = \DB::table('project_users')
                    ->where('project_id', $project->id)
                    ->pluck('user_id')
                    ->toArray();
            
                // Add the project owner if not already included
                $userIds = array_unique(array_merge($projectUserIds, [$project->owner_id]));
            
                // Fetch their names for the dropdown
                return \App\Models\User::whereIn('id', $userIds)
                    ->pluck('name', 'id');
            })
        
            ->multiple()
            ->searchable()
            ->minItems(1)
            ->required()
            ->helperText(__('Add users to the approval chain in order. The first user will be the initial approver.')),
    ]);
}

    protected function getTableQuery(): Builder|Relation
    {
        return parent::getTableQuery()
            ->where('is_active', true);
    }

    public static function table(Table $table): Table
    {
        return $table
        
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('chainUsers.name')
                    ->label(__('Approvers'))
                    ->formatStateUsing(function ($record) {
                        $users = $record->chainUsers;
                        $html = '<div class="flex flex-col gap-2">';
                        foreach ($users as $chainUser) {
                            $status = $chainUser->isApproved() 
                                ? '<span class="bg-success-500 text-white text-xs px-2 py-1 rounded">Approved</span>'
                                : ($chainUser->is_current 
                                    ? '<span class="bg-warning-500 text-white text-xs px-2 py-1 rounded">Current</span>'
                                    : '<span class="bg-gray-300 text-gray-700 text-xs px-2 py-1 rounded">Pending</span>');
                            
                            $html .= '<div class="flex items-center gap-2">';
                            $html .= '<span>#' . $chainUser->sequence . ' ' . $chainUser->user->name . '</span>';
                            $html .= $status;
                            if ($chainUser->approved_at) {
                                $html .= '<span class="text-xs text-gray-500">' . $chainUser->approved_at->format('Y-m-d H:i') . '</span>';
                            }
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                        return new HtmlString($html);
                    }),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label(__('Created By')),
                    
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active'))
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->form(function (RelationManager $livewire) {
                    $project = $livewire->getOwnerRecord();
                
                    // Get all user IDs linked to the current project
                    $projectUserIds = \DB::table('project_users')
                        ->where('project_id', $project->id)
                        ->pluck('user_id')
                        ->toArray();
                
                    // Include the project owner as well
                    $userIds = array_unique(array_merge($projectUserIds, [$project->owner_id]));
                
                    // Fetch user names for dropdown options
                    $projectUsers = \App\Models\User::whereIn('id', $userIds)
                        ->pluck('name', 'id')
                        ->toArray();
                
                    return [
                        Forms\Components\Select::make('users')
                            ->label(__('Approval Chain Users'))
                            ->options($projectUsers)
                            ->multiple()
                            ->searchable()
                            ->minItems(1)
                            ->required()
                            ->helperText(__('Add users to the approval chain in order. The first user will be the initial approver.')),
                    ];
                })
                
                    ->using(function (array $data, ?Model $record, RelationManager $livewire) {
                        // Access ownerRecord through the relation manager instance
                        $project = $livewire->getOwnerRecord(); 
                        
                        // Deactivate existing chains for this project
                        ApprovalChain::where('project_id', $project->id)->update(['is_active' => false]);

                        // Create the chain
                        $chain = ApprovalChain::create([
                            'project_id' => $project->id,
                            'created_by' => auth()->id(),
                            'is_active' => true
                        ]);

                        // Add users
                        foreach ($data['users'] as $sequence => $userId) {
                            $chain->chainUsers()->create([
                                'user_id' => $userId,
                                'sequence' => $sequence + 1,
                                'is_current' => $sequence === 0
                            ]);
                        }

                        return $chain;
                    })
                    ->after(function () {
                        Filament::notify('success', __('Approval chain created successfully'));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label(__('Approve & Forward'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => auth()->id() === $record->currentApprover()?->user_id)
                    ->requiresConfirmation()
                    ->modalHeading(__('Approve and Forward'))
                    ->action(function ($record) {
                        $record->approveAndForward();
                        Filament::notify('success', __('Project approved and forwarded'));
                    }),

                    
Tables\Actions\ViewAction::make('view')
->label(__('View Details'))
->icon('heroicon-o-eye')
->color('secondary')
->modalHeading(__('Approval Chain Details'))
->form([]) // ⬅ disables form content
->modalContent(function ($record) {
    $html = '<div class="space-y-4">';
    
    foreach ($record->chainUsers as $chainUser) {
        $status = $chainUser->isApproved() 
            ? '<span class="bg-success-500 text-white text-xs px-2 py-1 rounded">Approved</span>'
            : ($chainUser->is_current 
                ? '<span class="bg-warning-500 text-white text-xs px-2 py-1 rounded">Current Approver</span>'
                : '<span class="bg-gray-300 text-gray-700 text-xs px-2 py-1 rounded">Pending</span>');
        
        $html .= '<div class="border rounded p-3">';
        $html .= '<div class="flex justify-between items-center">';
        $html .= '<div><strong>#' . $chainUser->sequence . ' ' . e($chainUser->user->name) . '</strong></div>';
        $html .= $status;
        $html .= '</div>';
        
        if ($chainUser->approved_at) {
            $html .= '<div class="text-xs text-gray-500 mt-2">';
            $html .= __('Approved on:') . ' ' . e($chainUser->approved_at->format('Y-m-d H:i:s'));
            if ($chainUser->approved_by && $chainUser->approver) {
                $html .= '<br>' . __('By:') . ' ' . e($chainUser->approver->name);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }

    $html .= '</div>';
    return new HtmlString($html);
})
->modalWidth('xl'),

                    
                    Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record, $livewire) => 
                        $livewire->getOwnerRecord()->owner_id === auth()->id() ||
                        $livewire->getOwnerRecord()->users()
                            ->where('user_id', auth()->id())
                            ->where('role', config('system.projects.affectations.roles.can_manage'))
                            ->exists()
                    ),
                
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->authorize(function ($records) {
                        if (! $records || $records->isEmpty()) {
                            return false; // or true, depending on your logic
                        }
                    
                        $first = $records->first();
                    
                        return $first->ownerRecord->owner_id === auth()->id()
                            || $first->ownerRecord->users()
                                ->where('user_id', auth()->id())
                                ->where('role', config('system.projects.affectations.roles.can_manage'))
                                ->exists();
                    }),
            ]);
    }

    protected function canCreate(): bool
    {
        return $this->ownerRecord->owner_id === auth()->id()
            || $this->ownerRecord->users()->where('user_id', auth()->id())
                ->where('role', config('system.projects.affectations.roles.can_manage'))
                ->exists();
    }

    protected function canUpdate($record): bool
    {
        return $this->ownerRecord->owner_id === auth()->id()
            || $this->ownerRecord->users()->where('user_id', auth()->id())
                ->where('role', config('system.projects.affectations.roles.can_manage'))
                ->exists();
    }

    protected function canDelete($record): bool
    {
        return $this->ownerRecord->owner_id === auth()->id()
            || $this->ownerRecord->users()->where('user_id', auth()->id())
                ->where('role', config('system.projects.affectations.roles.can_manage'))
                ->exists();
    }
}

<?php

namespace App\Filament\Resources\ChatbotTrainingCandidates\Pages;

use App\Filament\Resources\ChatbotTrainingCandidates\ChatbotTrainingCandidateResource;
use App\Models\ChatbotTrainingCandidate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListChatbotTrainingCandidates extends ListRecords
{
    protected static string $resource = ChatbotTrainingCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tutorial')
                ->label('Cara menggunakan')
                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->color('gray')
                ->modalHeading('Tutorial Training Inbox')
                ->modalContent(fn () => view('filament.training-inbox-tutorial'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            Action::make('manualCandidate')
                ->label('Tambah contoh')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    Select::make('issue_type')->label('Jenis masalah')->options([
                        'manual_example' => 'Contoh pembelajaran manual',
                        'generic_off_topic' => 'Jawaban terlalu umum',
                        'low_confidence' => 'Chatbot tidak memahami',
                        'negative_feedback' => 'Feedback negatif',
                    ])->default('manual_example')->required(),
                    Textarea::make('user_message')->label('Contoh pesan pengguna')->rows(4)->required()->maxLength(4000),
                    Textarea::make('bot_response')->label('Jawaban chatbot yang salah/kurang tepat')->rows(4)->maxLength(6000),
                    Select::make('risk_level')->label('Risiko')->options([
                        'low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis',
                    ])->default('low')->required(),
                ])
                ->action(function (array $data): void {
                    ChatbotTrainingCandidate::query()->create([
                        'fingerprint' => hash('sha256', 'manual|'.auth()->id().'|'.microtime(true).'|'.$data['user_message']),
                        'source' => 'manual',
                        'issue_type' => $data['issue_type'],
                        'status' => 'new',
                        'priority' => 'normal',
                        'risk_level' => $data['risk_level'],
                        'user_message' => $data['user_message'],
                        'bot_response' => $data['bot_response'] ?? null,
                    ]);
                    Notification::make()->title('Contoh masuk ke Training Inbox')->body('Buka aksi Review untuk mengajarkan jawaban yang benar.')->success()->send();
                }),
        ];
    }
}

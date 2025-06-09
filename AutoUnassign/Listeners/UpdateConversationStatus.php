<?php

namespace Modules\AutoUnassign\Listeners;

use App\Events\CustomerReplied;
use App\Thread;

class UpdateConversationStatus
{
    public function handle(CustomerReplied $event)
    {
        $conversation = $event->conversation;

        $conversation->user_id = null;
        $conversation->status = \App\Conversation::STATUS_ACTIVE;
        $conversation->updateFolder();
        $conversation->save();
        $conversation->mailbox->updateFoldersCounters();

        \Log::info('[AutoUnassign] Conversation status updated.');
        
        $thread = $event->thread;
        $conversation = $thread->conversation;

        if (
            $thread->type === \App\Thread::TYPE_CUSTOMER &&
            $thread->state === \App\Thread::STATE_PUBLISHED &&
            !$thread->isForward()
        ) {
            $headers = $thread->headers;
            $subject = '';

            if (!empty($headers)) {
                preg_match('/^Subject:\s*(.+)$/mi', $headers, $m);
                $subject = trim($m[1] ?? '');
            }

            if (empty($subject)) {
                $subject = '(no subject)';
            }

            if (preg_match('/(?:Ticket\s*[-#]\s*|CW\s*Ticket\s*[-#]?\s*)(\d{6,8})/i', $subject, $matches)) {
                $ticketId = $matches[1];
                $ticketPrefix = "Ticket#{$ticketId}";

                if (strpos($conversation->subject, $ticketPrefix) === false) {
                    $conversation->subject = $ticketPrefix . ' ' . $conversation->subject;
                    $conversation->save();

                    \Log::info('[SUBJECT UPDATED - FROM CUSTOMER]', [
                        'conversation_id' => $conversation->id,
                        'new_subject' => $conversation->subject,
                    ]);
                } else {
                    \Log::info('[SUBJECT ALREADY HAS TICKET - FROM CUSTOMER]', [
                        'conversation_id' => $conversation->id,
                        'subject' => $conversation->subject,
                    ]);
                }
            } else {
                \Log::info('[TICKET ID NOT FOUND IN SUBJECT]', [
                    'conversation_id' => $conversation->id,
                    'original_subject' => $subject,
                ]);
            }
        }
        
        
    }
}

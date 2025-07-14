<?php
/**
 * Listener  : UpdateConversationStatus
 * Module    : AutoUnassign
 * Copyright : (c) 2025
 *
 * Menangani 3 template e‑mail ConnectWise: NOTE, INTERNAL NOTE, ASSIGN UPDATE.
 *  - NOTE            → tampilkan sr_detail_notes + avatar
 *  - INTERNAL NOTE   → seperti NOTE tetapi diberi header INTERNAL NOTE
 *  - ASSIGN UPDATE   → tampilkan blok Summary, detail status, ticket#, company …
 *                      + Assigned To / By / Date, dan tombol View Ticket.
 * Jika bukan tiga ini, body e‑mail dibiarkan utuh (Logic‑4).
 */

namespace Modules\AutoUnassign\Listeners;

use App\Events\CustomerReplied;
use App\Thread;
use Modules\AutoUnassign\Helpers\MailHelper;

class UpdateConversationStatus
{
    private const TYPE_NOTE          = 'note';
    private const TYPE_INTERNAL      = 'internal';
    private const TYPE_ASSIGN_UPDATE = 'assign_update';
   
    

    public function handle(CustomerReplied $event): void
    {
        $conv              = $event->conversation;
        $conv->user_id     = null;
        //$conv->status      = \App\Conversation::STATUS_ACTIVE;
        $systemUser = \App\User::find(12); // pastikan user ID 12 ada
        $conv->changeStatus(\App\Conversation::STATUS_ACTIVE, $systemUser);
        $conv->updateFolder();
        
        $conv->save();
        $conv->mailbox->updateFoldersCounters();

        $thread = $event->thread;
        if ($thread->type !== Thread::TYPE_CUSTOMER || $thread->state !== Thread::STATE_PUBLISHED || $thread->isForward()) {
            return;
        }

        $this->maybePrependTicketNumber($thread, $conv);

        $type = $this->detectTemplateType($thread->body);
        if (!in_array($type, [self::TYPE_NOTE, self::TYPE_INTERNAL, self::TYPE_ASSIGN_UPDATE], true)) {
            return;
        }

        $clean = self::buildCleanBlock($thread->body, $type);
        if (!$clean || $clean === $thread->body) {
            return;
        }

        if (!$thread->body_original) {
            $thread->body_original = $thread->body;
        }
        $thread->body              = $clean;
        $thread->edited_by_user_id = $systemUser->id; // Buatkan user baru supaya terdeteksi bahwa ini otomatis di update
        $thread->edited_at         = now();
        $conv->setPreview($clean);
        $conv->save();
        $thread->save();
    }


private function detectTemplateType(string $html): string {
    $lower = strtolower($html);

    // Trigger utama
    $hasReplyTrigger = strpos($lower, '--reply above this line to respond') !== false;

    // Logic 1: Assign Update (lama + tambahan kamu)
    if ($hasReplyTrigger && (
        strpos($lower, 'you have been assigned') !== false ||
        strpos($lower, 'this ticket has been updated by wowrack technologies') !== false ||
        strpos($lower, 'a response to this ticket has been received through the email connector') !== false ||
        strpos($lower, 'this ticket has been updated by psa admin') !== false
    )) {
        return self::TYPE_ASSIGN_UPDATE;
    }

    // Logic 2: Internal Note — cek apakah ini "Internal Only"
    if (strpos($lower, '--reply above this line to respond (internal only)') !== false) {
        return self::TYPE_INTERNAL;
    }

    // Default: Note biasa
    return self::TYPE_NOTE;
}

    // private function maybePrependTicketNumber(Thread $thread, \App\Conversation $conv): void
    // {
    //     preg_match('/^Subject:\\s*(.+)$/mi', $thread->headers ?? '', $m);
    //     $subRaw = trim($m[1] ?? '');
    //     if (preg_match('/(?:(?:CW|Wowrack)?\s*(?:ID\s*)?(?:Support\s*)?Ticket\s*[-#:]?|CW\s*Ticket\s*[-#:]?)\s*(\d{5,8})/i', $subRaw, $mm) && strpos($conv->subject, "Ticket#{$mm[1]}") === false) {
    //         $conv->subject = "Ticket#{$mm[1]} {$conv->subject}";
    //         $conv->save();
    //     }
    // }

    private function maybePrependTicketNumber(Thread $thread, \App\Conversation $conv): void
    {
        preg_match('/^Subject:\\s*(.+)$/mi', $thread->headers ?? '', $m);
        $rawSubject = trim($m[1] ?? '');
        $decodedSubject = MailHelper::decodeMimeHeader($rawSubject);

        if (stripos($conv->subject, 'ticket#') !== false) {
            return;
        }

        if (preg_match('/(?:(?:CW|Wowrack)?\s*(?:ID\s*)?(?:Support\s*)?Ticket\s*[-#:]?|CW\s*Ticket\s*[-#:]?)\s*(\d{5,8})/i', $decodedSubject, $mm)) {
            $ticketNum = $mm[1];
            $conv->subject = "Ticket#{$ticketNum} {$conv->subject}";
            $conv->save();
        }
    }

  

    private static function buildCleanBlock(string $html, string $type): string
    {
        // libxml_use_internal_errors(true);
        // $dom = new \DOMDocument('1.0', 'UTF-8');
        // $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        // $xp  = new \DOMXPath($dom);

        libxml_use_internal_errors(true);

$dom = new \DOMDocument('1.0', 'UTF-8');
// Tambahkan deklarasi encoding UTF-8 langsung di depan HTML
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

$xp  = new \DOMXPath($dom);

        if ($type === self::TYPE_ASSIGN_UPDATE) {
            $table = $xp->query('//table[contains(@id,"templateSection")]')->item(0);
            if (!$table) { return $html; }

            $summary = '';
            $sumValueTd = $xp->query(
                './/td[normalize-space(text()) = "Summary:"]/parent::tr/following-sibling::tr[1]/td[1]',
                $table
            )->item(0);
            if ($sumValueTd) {
                $summary = trim($sumValueTd->textContent);
            }

            $fields = ['Status:', 'Ticket #', 'Company:', 'Contact:', 'Phone:', 'Address:',
                       'Assigned To:', 'Assigned By:', 'Date Required:', 'Date Assigned:'];
            $infoRows = self::renderRow('ASSIGN UPDATE', '');
            if ($summary) {
                $infoRows .= self::renderRow('Summary:', $summary, true);
            }
            foreach ($fields as $label) {
                $value = self::getCellText($xp, $label);
                if ($value) {
                    $infoRows .= self::renderRow($label, $value);
                }
            }

            $btn = $xp->query('.//a[contains(translate(text(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"view ticket")]', $table)->item(0);
            $btnHtml = '';
            if ($btn) {
                $href = $btn->getAttribute('href');
                $btnHtml = <<<HTML
<tr><td colspan="2" style="text-align:center;padding:20px 0;">
  <a href="{$href}" style="background:#026ccf;border-radius:4px;color:#fff;font-weight:bold;text-decoration:none;padding:10px 24px;display:inline-block;text-transform:uppercase;">View Ticket</a>
</td></tr>
HTML;
            }

            return <<<HTML
<div class="clean-thread-wrapper">
  <table style="width:100%;font-family:Roboto,Arial,sans-serif;font-size:14px;background:#f9f9f9;border:1px solid #ddd;">
    {$infoRows}
    {$btnHtml}
  </table>
</div>
HTML;
        }

        $xpNotes = $xp;
        $notesElm = null;
        if ($type === self::TYPE_INTERNAL) {
            $header = $xpNotes->query('//*[translate(normalize-space(.),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="internal"]')->item(0);
            if ($header) {
                $notesElm = $xpNotes->query('following::*[contains(@id,"sr_detail_notes")][1]', $header)->item(0);
            }
        }
        if (!$notesElm) { $notesElm = $xpNotes->query('//*[contains(@id,"sr_detail_notes")][1]')->item(0); }
        if (!$notesElm) { return $html; }

        $rowContent = self::findAncestor($notesElm,'tr') ?: $notesElm;
        $nameNode = $xp->query('//*[contains(@id,"avatar")]//*[contains(@style,"font-weight:bold")][1]')->item(0);
        $timeNode = $xp->query('//*[contains(@id,"avatar")]//td[contains(text(),":")][1]')->item(0);
        $name = $nameNode ? trim($nameNode->textContent) : 'Customer';
        $time = $timeNode ? trim($timeNode->textContent) : date('n/j/Y g:i A');

        $rows = '';
        if ($type === self::TYPE_INTERNAL) { $rows .= self::renderInternalHeaderRow(); }
        $rows .= self::renderAvatarRow($name,$time);
        $rows .= $dom->saveHTML($rowContent);

        return <<<HTML
<div class="clean-thread-wrapper"><table style="width:100%;font-family:Roboto,Arial,sans-serif;font-size:14px;">{$rows}</table></div>
HTML;
    }

    private static function getCellText(\DOMXPath $xp, string $label): string
    {
        $rows = $xp->query('//tr[td[contains(normalize-space(.), "' . $label . '")]]');
        foreach ($rows as $row) {
            $tds = $row->getElementsByTagName('td');
            if ($tds->length === 2) { return trim($tds->item(1)->textContent); }
        }
        return '';
    }

    private static function renderRow(string $label,string $value,bool $boldSummary=false): string
    {
        $styleLabel = 'padding:6px 16px;font-weight:bold;width:140px;';
        $styleValue = 'padding:6px 0px;';

         // Jika ini baris khusus judul (misalnya ASSIGN UPDATE)
    if ($label === 'ASSIGN UPDATE') {
        return '<tr><td colspan="2" style="background:#f5f5f5;font-weight:bold;text-align:center;padding:10px;color:#333;">ASSIGN UPDATE</td></tr>';
    }

        if ($boldSummary) {
            $styleValue .= 'font-size:16px;font-weight:bold;color:#000;';
        }
        return "<tr><td style=\"$styleLabel\">$label</td><td style=\"$styleValue\">$value</td></tr>";
    }

    private static function renderInternalHeaderRow(): string
    {
        return '<tr><td colspan="2" style="padding:8px 16px;text-align:center;font-weight:bold;background:#ffe9c6;color:#b26a00;">INTERNAL NOTE</td></tr>';
    }

    private static function renderAvatarRow(string $name, string $time): string
    {
        $placeholder = '<div style="width:32px;height:32px;border-radius:50%;background:#d0d0d0;"></div>';

        return <<<HTML
<tr>
  <td style="padding:0 10px 0 16px;text-align:right;vertical-align:top;font-weight:bold;">
      {$name}<br>
      <span style="font-weight:normal;color:#9e9e9e;font-size:12px;">{$time}</span>
  </td>
  <td style="width:40px;text-align:center;padding:0 16px 4px 0;">{$placeholder}</td>
</tr>
HTML;
    }

    private static function findAncestor(\DOMNode $node, string $tagName): ?\DOMNode
    {
        while ($node && $node->nodeType === XML_ELEMENT_NODE) {
            if (strtolower($node->nodeName) === strtolower($tagName)) {
                return $node;
            }
            $node = $node->parentNode;
        }
        return null;
    }
}

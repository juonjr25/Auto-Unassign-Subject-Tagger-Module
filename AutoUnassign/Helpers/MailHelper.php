<?php

namespace Modules\AutoUnassign\Helpers;

class MailHelper
{
    public static function decodeMimeHeader(?string $header): string
    {
        if (!$header) return '';

        // Coba decode dengan iconv
        $decoded = @iconv_mime_decode($header, 0, 'UTF-8');
        if ($decoded !== false && trim($decoded) !== '') {
            return trim($decoded);
        }

        // Fallback manual jika iconv gagal
        return preg_replace_callback(
            '/=\?([^?]+)\?([BQbq])\?([^?]+)\?=/',
            function ($matches) {
                $charset = strtoupper($matches[1]);
                $encoding = strtoupper($matches[2]);
                $encodedText = $matches[3];

                if ($encoding === 'B') {
                    $text = base64_decode($encodedText);
                } elseif ($encoding === 'Q') {
                    $text = quoted_printable_decode(str_replace('_', ' ', $encodedText));
                } else {
                    return $matches[0];
                }

                return @iconv($charset, 'UTF-8//IGNORE', $text) ?: $text;
            },
            $header
        );
    }
}
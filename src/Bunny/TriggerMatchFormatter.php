<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny;

/**
 * Pretty-prints the TriggerMatch value of a Shield event. bunny.net cuts the
 * value off after 1000 characters, so the JSON is usually incomplete and
 * json_decode() fails; this formatter indents it token by token instead and
 * unwraps string values that themselves contain JSON (Livewire snapshots).
 */
class TriggerMatchFormatter
{
    private const int INDENT_WIDTH = 2;

    /**
     * Returns null when the value does not look like JSON at all.
     */
    public function format(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        if (! $this->looksLikeJson($trimmed)) {
            return null;
        }

        return $this->prettify($trimmed, 0);
    }

    private function looksLikeJson(string $value): bool
    {
        return $value !== '' && ($value[0] === '{' || $value[0] === '[');
    }

    private function prettify(string $json, int $baseDepth): string
    {
        $output = '';
        $depth = $baseDepth;
        $length = strlen($json);
        $position = 0;

        while ($position < $length) {
            $char = $json[$position];

            if ($char === '"') {
                [$escaped, $closed, $position] = $this->readString($json, $position + 1);
                $output .= $this->renderString($escaped, $closed, $depth);

                continue;
            }

            if ($char === '{' || $char === '[') {
                $closing = $char === '{' ? '}' : ']';
                $next = $this->nextNonSpace($json, $position + 1);

                if ($next !== null && $json[$next] === $closing) {
                    $output .= $char.$closing;
                    $position = $next + 1;

                    continue;
                }

                $depth++;
                $output .= $char."\n".$this->indent($depth);
                $position++;

                continue;
            }

            if ($char === '}' || $char === ']') {
                $depth = max($baseDepth, $depth - 1);
                $output .= "\n".$this->indent($depth).$char;
                $position++;

                continue;
            }

            if ($char === ',') {
                $output .= ",\n".$this->indent($depth);
                $position++;

                continue;
            }

            if ($char === ':') {
                $output .= ': ';
                $position++;

                continue;
            }

            if (! ctype_space($char)) {
                $output .= $char;
            }

            $position++;
        }

        return $output;
    }

    /**
     * Reads a JSON string starting after its opening quote.
     *
     * @return array{0: string, 1: bool, 2: int} the raw escaped content, whether the closing quote was found, the next position
     */
    private function readString(string $json, int $position): array
    {
        $length = strlen($json);
        $buffer = '';

        while ($position < $length) {
            $char = $json[$position];

            if ($char === '\\' && $position + 1 < $length) {
                $buffer .= $char.$json[$position + 1];
                $position += 2;

                continue;
            }

            if ($char === '"') {
                return [$buffer, true, $position + 1];
            }

            $buffer .= $char;
            $position++;
        }

        return [$buffer, false, $position];
    }

    private function renderString(string $escaped, bool $closed, int $depth): string
    {
        $decoded = json_decode('"'.$this->withoutDanglingEscape($escaped).'"');

        if (! is_string($decoded)) {
            return '"'.$escaped.($closed ? '"' : '…');
        }

        if ($this->looksLikeJson(trim($decoded)) && strlen(trim($decoded)) > 1) {
            return $this->prettify(trim($decoded), $depth);
        }

        $encoded = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $closed ? $encoded : substr($encoded, 0, -1).'…';
    }

    private function withoutDanglingEscape(string $escaped): string
    {
        return preg_replace('/\\\\(u[0-9a-fA-F]{0,3})?$/', '', $escaped) ?? $escaped;
    }

    private function nextNonSpace(string $json, int $position): ?int
    {
        $length = strlen($json);

        while ($position < $length && ctype_space($json[$position])) {
            $position++;
        }

        return $position < $length ? $position : null;
    }

    private function indent(int $depth): string
    {
        return str_repeat(' ', $depth * self::INDENT_WIDTH);
    }
}

<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Bunny\TriggerMatchFormatter;

beforeEach(function (): void {
    $this->formatter = new TriggerMatchFormatter;
});

test('returns null for values that are not json-like', function (): void {
    expect($this->formatter->format(null))->toBeNull()
        ->and($this->formatter->format('SELECT * FROM users'))->toBeNull();
});

test('indents json and unwraps nested json strings', function (): void {
    $value = '{"_token":"abc","components":[{"snapshot":"{\"data\":{\"message\":\"hi, there\"}}","empty":{}}]}';

    expect($this->formatter->format($value))->toBe(<<<'TXT'
    {
      "_token": "abc",
      "components": [
        {
          "snapshot": {
            "data": {
              "message": "hi, there"
            }
          },
          "empty": {}
        }
      ]
    }
    TXT);
});

test('formats json that bunny cut off mid-string', function (): void {
    $value = '{"components":[{"snapshot":"{\"data\":{\"title\":\"unfinished tex';

    expect($this->formatter->format($value))->toBe(<<<'TXT'
    {
      "components": [
        {
          "snapshot": {
            "data": {
              "title": "unfinished tex…
    TXT);
});

test('survives a dangling escape sequence at the cut-off point', function (): void {
    expect($this->formatter->format('{"a":"x\\'))->toBe("{\n  \"a\": \"x…");
});

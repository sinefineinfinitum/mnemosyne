<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

/**
 * @internal
 */
final class TokenParser
{
    public const KEYWORDS = [
        'final',
        'abstract',
        'static',
        'readonly',
    ];

    /**
     * Parses a declaration string in the format "name:type=value".
     * Handles generics (angle brackets) for type and value parsing.
     *
     * @throws SyntaxException If the angle brackets in the declaration are unbalanced.
     */
    public static function parseTypedDeclaration(string $declarationString): TypedDeclaration
    {
        $colonPos = false;
        $equalPos = false;
        $depth = 0;
        $len = strlen($declarationString);

        for ($i = 0; $i < $len; $i++) {
            $char = $declarationString[$i];
            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                if ($colonPos === false) {
                    $colonPos = $i;
                }
            } elseif ($char === '=' && $depth === 0) {
                if ($equalPos === false) {
                    $equalPos = $i;
                }
            }
        }

        if ($depth !== 0) {
            throw SyntaxException::atLine(
                sprintf('Unbalanced angle brackets in declaration "%s"', $declarationString),
                0,
                $declarationString,
            );
        }

        $dataType = null;
        $value = null;

        if ($equalPos !== false && ($colonPos === false || $equalPos < $colonPos)) {
            $nameAndKeywords = substr($declarationString, 0, $equalPos);
            $value = substr($declarationString, $equalPos + 1);
        } elseif ($colonPos !== false && ($equalPos === false || $colonPos < $equalPos)) {
            $nameAndKeywords = substr($declarationString, 0, $colonPos);
            $dataType = substr($declarationString, $colonPos + 1, $equalPos !== false ? $equalPos - $colonPos - 1 : null);
            if ($equalPos !== false) {
                $value = substr($declarationString, $equalPos + 1);
            }
        } else {
            $nameAndKeywords = $declarationString;
        }

        return new TypedDeclaration(
            trim($nameAndKeywords),
            $dataType !== null ? trim($dataType) : null,
            $value !== null ? trim($value) : null,
        );
    }

    /**
     * Splits a string into a name and an array of attributes (keywords).
     *
     * The first whitespace-delimited element that is not a known keyword is the name.
     * Known keywords (see KEYWORDS) may appear before or after the name.
     *
     * @throws SyntaxException When the input is empty or a token is not a known keyword.
     */
    public static function splitNameAndAttributes(string $inputString): NameAndAttributes
    {
        $inputString = trim($inputString);

        if ($inputString === '') {
            throw new SyntaxException('Name cannot be empty');
        }

        $words = preg_split('/\s+/', $inputString);

        if ($words === false) {
            throw new SyntaxException('Name cannot be empty');
        }

        $name = null;
        $attributes = [];
        $allKeywords = true;

        foreach ($words as $word) {
            if (in_array($word, self::KEYWORDS, true)) {
                $attributes[] = $word;
            } else {
                $allKeywords = false;
                if ($name === null) {
                    $name = $word;
                } else {
                    throw new SyntaxException(
                        sprintf('Unknown attribute "%s", expected one of: %s', $word, implode(', ', self::KEYWORDS))
                    );
                }
            }
        }

        if ($name === null) {
            if ($allKeywords && !empty($attributes)) {
                $name = array_shift($attributes);
            } else {
                throw new SyntaxException('Name cannot be empty');
            }
        }

        return new NameAndAttributes($name, $attributes);
    }
}

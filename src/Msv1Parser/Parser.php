<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser;

use SineFine\Mnemosyne\Msv1Parser\Ast\CallNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\Document;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\ParameterNode;
use SineFine\Mnemosyne\Msv1Parser\Contracts\ParserInterface;
use SineFine\Mnemosyne\Msv1Parser\Internal\Lexer;
use SineFine\Mnemosyne\Msv1Parser\Internal\Line;
use SineFine\Mnemosyne\Msv1Parser\Internal\ParserState;
use SineFine\Mnemosyne\Msv1Parser\Internal\TokenParser;

class Parser implements ParserInterface
{
    public const VERSION = '1.0';

    private Lexer $lexer;

    public function __construct(?Lexer $lexer = null)
    {
        $this->lexer = $lexer ?? new Lexer();
    }

    public function parse(string $content): Document
    {
        $document = new Document();
        $document->parserVersion = self::VERSION;

        $state = new ParserState();
        foreach ($this->lexer->tokenize($content) as $line) {
            if ($line->indentation === 0) {
                $this->parseTopLevelLine($line, $document, $state);
            } elseif ($line->indentation === Lexer::INDENT_WIDTH) {
                $this->parseIndentedLine($line, $state);
            }
        }

        return $document;
    }

    private function parseTopLevelLine(Line $line, Document $document, ParserState $state): void
    {
        $trimmed = $line->trimmed;

        if (str_starts_with($trimmed, EntityNode::ENTITY_START)) {
            $entity = $this->parseEntityDirective($line);
            $document->entities[] = $entity;
            $state->openEntity($entity);
            return;
        }

        if (EntityNode::isRelationMarker($trimmed[0])) {
            $this->parseEntityRelationDirective($line, $state->entity());
            return;
        }

        if ($state->entity() === null) {
            throw $this->syntaxError('Member declaration found without active entity', $line);
        }

        $entity = $state->entity();
        $member = $this->parseMemberDirective($line, $entity);
        $entity->members[] = $member;

        if ($member->type === 'method' || $member->type === 'function') {
            $state->openMethod($member);
        } else {
            $state->closeMethod();
        }
    }

    private function parseIndentedLine(Line $line, ParserState $state): void
    {
        if ($state->method() === null) {
            throw $this->syntaxError('Indented line found without active method/function block', $line);
        }

        $this->parseMethodChildDirective($line, $state->method());
    }

    private function parseEntityDirective(Line $line): EntityNode
    {
        $entityType = EntityNode::detectType($line->trimmed);
        if ($entityType === null) {
            throw $this->syntaxError('Unknown or invalid entity type in directive', $line);
        }

        $rest = substr($line->trimmed, strlen('@' . $entityType));
        if ($rest !== '' && !str_starts_with($rest, ' ')) {
            throw $this->syntaxError('Entity directive must be followed by space', $line);
        }

        $rest = trim($rest);
        if ($rest === '') {
            throw $this->syntaxError('Entity name is required', $line);
        }

        $parsed = TokenParser::splitNameAndAttributes($rest);

        $entity = new EntityNode($entityType, $parsed->name);
        $entity->attributes = $parsed->attributes;
        return $entity;
    }

    private function parseEntityRelationDirective(Line $line, ?EntityNode $entity): void
    {
        if ($entity === null) {
            throw $this->syntaxError('Inheritance/trait directive found without active entity', $line);
        }

        if (!$entity->canHaveRelations()) {
            throw $this->syntaxError('Inheritance/trait directive not allowed in @file context', $line);
        }

        $target = trim(substr($line->trimmed, 1));

        if ($target === '') {
            throw $this->syntaxError('Inheritance/trait directive cannot be empty', $line);
        }

        $entity->addRelation($line->trimmed[0], $target);
    }

    private function parseMemberDirective(Line $line, EntityNode $currentEntity): MemberNode
    {
        $trimmed = $line->trimmed;
        $firstChar = $trimmed[0];
        if (!MemberNode::isValidSymbol($firstChar)) {
            throw $this->syntaxError('Invalid line starting symbol', $line);
        }

        if ($firstChar === '~' && !$currentEntity->canHaveEnumCases()) {
            throw $this->syntaxError("Enum case '~' not allowed in @file context", $line);
        }

        $vis = MemberNode::parseVisibilityPrefix(substr($trimmed, 1));
        if ($vis['visibility'] !== null && !$currentEntity->canHaveVisibility()) {
            throw $this->syntaxError('Visibility modifiers not allowed in @file context', $line);
        }

        $declaration = TokenParser::parseTypedDeclaration($vis['body']);
        $parsed = TokenParser::splitNameAndAttributes($declaration->nameAndKeywords);

        if ($parsed->name === '') {
            throw $this->syntaxError('Member name cannot be empty', $line);
        }

        $member = new MemberNode(
            $parsed->name,
            MemberNode::resolveType($firstChar, $currentEntity),
            $currentEntity,
        );
        $member->visibility = $vis['visibility'];
        $member->attributes = $parsed->attributes;
        $member->dataType = $declaration->dataType;
        $member->returnType = $declaration->dataType;
        $member->value = $declaration->value;

        return $member;
    }

    private function parseMethodChildDirective(Line $line, MemberNode $currentMethod): void
    {
        $trimmed = $line->trimmed;
        if (str_starts_with($trimmed, '^')) {
            $this->parseInstantiationDirective($line, $currentMethod);
            return;
        }

        if (str_starts_with($trimmed, ':')) {
            $this->parseReturnTypeDirective($line, $currentMethod);
            return;
        }

        if (str_starts_with($trimmed, CallNode::MARKER_STRONG) || str_starts_with($trimmed, CallNode::MARKER_WEAK)) {
            $this->parseCallDirective($line, $currentMethod);
            return;
        }

        $this->parseParameterDirective($line, $currentMethod);
    }

    private function parseInstantiationDirective(Line $line, MemberNode $currentMethod): void
    {
        $instantiatedClass = trim(substr($line->trimmed, 1));
        if ($instantiatedClass === '') {
            throw $this->syntaxError('Instantiation class name cannot be empty', $line);
        }
        $currentMethod->creates[] = $instantiatedClass;
    }

    private function parseReturnTypeDirective(Line $line, MemberNode $currentMethod): void
    {
        $retType = trim(substr($line->trimmed, 1));
        if ($retType === '') {
            throw $this->syntaxError('Return type cannot be empty', $line);
        }
        $currentMethod->returnType = $retType;
    }

    private function parseParameterDirective(Line $line, MemberNode $currentMethod): void
    {
        $prefix = ParameterNode::parsePrefix($line->trimmed);
        if ($prefix === null) {
            throw $this->syntaxError('Invalid child line format', $line);
        }

        $declaration = TokenParser::parseTypedDeclaration($prefix['body']);
        $parsed = TokenParser::splitNameAndAttributes($declaration->nameAndKeywords);

        if ($parsed->name === '') {
            throw $this->syntaxError('Parameter name cannot be empty', $line);
        }

        $paramNode = new ParameterNode($parsed->name);
        $paramNode->type = $declaration->dataType;
        $paramNode->byRef = $prefix['byRef'];
        $paramNode->variadic = $prefix['variadic'];
        $paramNode->value = $declaration->value;

        $currentMethod->parameters[] = $paramNode;
    }

    private function parseCallDirective(Line $line, MemberNode $currentMethod): void
    {
        $callNode = CallNode::parseCall($line->trimmed);
        if ($callNode === null) {
            throw $this->syntaxError('Invalid call directive', $line);
        }
        $currentMethod->calls[] = $callNode;
    }

    private function syntaxError(string $message, Line $line): SyntaxException
    {
        return SyntaxException::atLine($message, $line->number + 1, $line->raw);
    }
}

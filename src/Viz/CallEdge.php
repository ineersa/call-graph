<?php declare(strict_types=1);

namespace CallGraph\Viz;

final class CallEdge
{
    public function __construct(
        public readonly string $callerClass,
        public readonly string $callerMember,
        public readonly string $callerKind,
        public readonly string $calleeClass,
        public readonly string $calleeMember,
        public readonly string $calleeKind,
        public readonly string $callType,
        public readonly string $file,
        public readonly int $line,
        public readonly bool $unresolved
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        if (isset($row['callingClass'], $row['callingMethod'], $row['calledClass'], $row['calledMethod'])) {
            return new self(
                self::stringValue($row['callingClass']),
                self::stringValue($row['callingMethod']),
                'method',
                self::stringValue($row['calledClass']),
                self::stringValue($row['calledMethod']),
                'method',
                'method',
                '',
                0,
                false
            );
        }

        return new self(
            self::stringValue($row['callerClass'] ?? ''),
            self::stringValue($row['callerMember'] ?? '{unknown}'),
            self::stringValue($row['callerKind'] ?? 'unknown'),
            self::stringValue($row['calleeClass'] ?? ''),
            self::stringValue($row['calleeMember'] ?? '{unknown}'),
            self::stringValue($row['calleeKind'] ?? 'unknown'),
            self::stringValue($row['callType'] ?? 'unknown'),
            self::stringValue($row['file'] ?? ''),
            self::intValue($row['line'] ?? 0),
            self::boolValue($row['unresolved'] ?? false)
        );
    }

    public function classCallerLabel(): string
    {
        if ($this->callerClass !== '') {
            return $this->callerClass;
        }

        return 'function ' . $this->callerMember;
    }

    public function classCalleeLabel(): string
    {
        if ($this->calleeClass !== '') {
            return $this->calleeClass;
        }

        return 'function ' . $this->calleeMember;
    }

    public function methodCallerLabel(): string
    {
        if ($this->callerClass !== '') {
            return $this->callerClass . '::' . $this->callerMember;
        }

        return 'function ' . $this->callerMember;
    }

    public function methodCalleeLabel(): string
    {
        if ($this->calleeClass !== '') {
            return $this->calleeClass . '::' . $this->calleeMember;
        }

        return 'function ' . $this->calleeMember;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function intValue(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private static function boolValue(mixed $value): bool
    {
        return is_bool($value) ? $value : false;
    }
}

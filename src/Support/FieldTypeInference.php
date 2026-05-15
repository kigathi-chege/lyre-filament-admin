<?php

namespace Lyre\Filament\Admin\Support;

final class FieldTypeInference
{
    public const TYPE_ID = 'id';

    public const TYPE_FOREIGN_KEY = 'foreign_key';

    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_FLOAT = 'float';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DATE = 'date';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_TIMESTAMP_SYSTEM = 'timestamp_system';

    public const TYPE_SOFT_DELETE = 'soft_delete';

    public const TYPE_JSON = 'json';

    public const TYPE_EMAIL = 'email';

    public const TYPE_URL = 'url';

    public const TYPE_ENUM = 'enum';

    public const TYPE_UUID = 'uuid';

    public const TYPE_PASSWORD = 'password';

    public const TYPE_UNKNOWN = 'unknown';

    public static function infer(
        string $columnName,
        ?string $dbType,
        ?string $cast,
        bool $isPrimary,
        bool $isForeign,
        bool $isTimestamp,
        bool $isSoftDelete
    ): string {
        if ($isPrimary) {
            return self::TYPE_ID;
        }

        if ($isSoftDelete) {
            return self::TYPE_SOFT_DELETE;
        }

        if ($isTimestamp) {
            return self::TYPE_TIMESTAMP_SYSTEM;
        }

        if ($isForeign || str_ends_with($columnName, '_id')) {
            return self::TYPE_FOREIGN_KEY;
        }

        $lowerName = strtolower($columnName);

        if ($lowerName === 'email' || str_ends_with($lowerName, '_email')) {
            return self::TYPE_EMAIL;
        }

        if ($lowerName === 'password' || str_contains($lowerName, 'password')) {
            return self::TYPE_PASSWORD;
        }

        if (in_array($lowerName, ['url', 'website', 'link', 'href'], true)
            || str_ends_with($lowerName, '_url')) {
            return self::TYPE_URL;
        }

        if ($lowerName === 'uuid' || str_ends_with($lowerName, '_uuid')) {
            return self::TYPE_UUID;
        }

        if (is_string($cast)) {
            $castLower = strtolower($cast);

            if ($castLower === 'boolean' || $castLower === 'bool') {
                return self::TYPE_BOOLEAN;
            }

            if ($castLower === 'array' || $castLower === 'json' || $castLower === 'object' || $castLower === 'collection') {
                return self::TYPE_JSON;
            }

            if ($castLower === 'integer' || $castLower === 'int') {
                return self::TYPE_INTEGER;
            }

            if ($castLower === 'float' || $castLower === 'double' || $castLower === 'real') {
                return self::TYPE_FLOAT;
            }

            if ($castLower === 'decimal' || str_starts_with($castLower, 'decimal:')) {
                return self::TYPE_FLOAT;
            }

            if ($castLower === 'datetime' || $castLower === 'date' || str_starts_with($castLower, 'datetime:') || str_starts_with($castLower, 'date:')) {
                return $castLower === 'date' || str_starts_with($castLower, 'date:')
                    ? self::TYPE_DATE
                    : self::TYPE_DATETIME;
            }

            if (class_exists($cast) && function_exists('enum_exists') && enum_exists($cast)) {
                return self::TYPE_ENUM;
            }
        }

        $type = strtolower((string) $dbType);

        return match (true) {
            $type === 'boolean', $type === 'bool', $type === 'tinyint' => self::TYPE_BOOLEAN,
            in_array($type, ['integer', 'int', 'bigint', 'smallint', 'mediumint', 'int2', 'int4', 'int8'], true) => self::TYPE_INTEGER,
            in_array($type, ['float', 'double', 'real', 'decimal', 'numeric'], true), str_starts_with($type, 'decimal'), str_starts_with($type, 'numeric') => self::TYPE_FLOAT,
            $type === 'date' => self::TYPE_DATE,
            in_array($type, ['datetime', 'timestamp', 'timestamptz', 'datetimetz'], true), str_starts_with($type, 'timestamp') => self::TYPE_DATETIME,
            in_array($type, ['json', 'jsonb'], true) => self::TYPE_JSON,
            in_array($type, ['text', 'longtext', 'mediumtext', 'tinytext'], true) => self::TYPE_TEXT,
            in_array($type, ['uuid'], true) => self::TYPE_UUID,
            in_array($type, ['string', 'varchar', 'char', 'character varying', 'character'], true), str_starts_with($type, 'varchar'), str_starts_with($type, 'character') => self::TYPE_STRING,
            default => self::TYPE_UNKNOWN,
        };
    }
}

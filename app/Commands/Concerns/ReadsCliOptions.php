<?php

namespace App\Commands\Concerns;

use CodeIgniter\CLI\CLI;

/**
 * Reads a spark command option whichever way the operator typed it.
 *
 * CodeIgniter's CLI parser stores:
 *   --flag            as  ['flag' => null]
 *   --name value      as  ['name' => 'value']
 *   --name=value      as  ['name=value' => null]   (the "=" stays in the key)
 *
 * This trait normalises all three shapes so `--confirm=RESTORE` behaves exactly
 * like `--confirm RESTORE`, as the command usage strings advertise.
 *
 * A trait (not a BaseCommand subclass) on purpose: the framework's command
 * discovery only registers classes that extend BaseCommand.
 */
trait ReadsCliOptions
{
    /**
     * Value of an option, or null when it was not supplied (or was a bare flag).
     *
     * @param array<int|string, string|null> $params
     */
    protected function optionValue(array $params, string $name): ?string
    {
        if (array_key_exists($name, $params) && is_string($params[$name])) {
            return $params[$name];
        }

        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, $name . '=')) {
                return substr($key, strlen($name) + 1);
            }
        }

        $option = CLI::getOption($name);

        return is_string($option) ? $option : null;
    }

    /**
     * True when a boolean flag is present (bare `--flag`, `--flag=true`, …).
     *
     * @param array<int|string, string|null> $params
     */
    protected function hasFlag(array $params, string $name): bool
    {
        if (array_key_exists($name, $params)) {
            return true;
        }

        foreach ($params as $key => $value) {
            if (is_string($key) && str_starts_with($key, $name . '=')) {
                return ! in_array(strtolower(substr($key, strlen($name) + 1)), ['0', 'false', 'no', 'off'], true);
            }
        }

        return CLI::getOption($name) !== null;
    }
}

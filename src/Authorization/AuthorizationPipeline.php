<?php

namespace Lyre\Filament\Admin\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthorizationPipeline
{
    /**
     * Request-scoped memo of resolved Shield permission strings (or null when
     * Shield doesn't know about the model). The helper internally iterates all
     * registered resources and can throw — both of which are very expensive
     * when Filament's navigation invokes can*() across dozens of resources.
     *
     * @var array<string, ?string>
     */
    private array $shieldPermissionMemo = [];

    /**
     * Request-scoped memo of policy presence per model.
     *
     * @var array<string, bool>
     */
    private array $policyPresenceMemo = [];

    /**
     * Request-scoped memo of class-level (record-less) decisions.
     *
     * @var array<string, bool>
     */
    private array $classDecisionMemo = [];

    public function check(string $modelClass, string $ability, ?Model $record = null): bool
    {
        if ($record === null) {
            $key = $modelClass.'|'.$ability;
            if (array_key_exists($key, $this->classDecisionMemo)) {
                return $this->classDecisionMemo[$key];
            }
        }

        $decision = $this->resolve($modelClass, $ability, $record);

        if ($record === null) {
            $this->classDecisionMemo[$modelClass.'|'.$ability] = $decision;
        }

        return $decision;
    }

    private function resolve(string $modelClass, string $ability, ?Model $record): bool
    {
        $policyResult = $this->policyCheck($modelClass, $ability, $record);
        if ($policyResult !== null) {
            return $policyResult;
        }

        $shieldResult = $this->shieldCheck($modelClass, $ability);
        if ($shieldResult !== null) {
            return $shieldResult;
        }

        return $this->fallback();
    }

    private function policyCheck(string $modelClass, string $ability, ?Model $record): ?bool
    {
        if (! array_key_exists($modelClass, $this->policyPresenceMemo)) {
            $this->policyPresenceMemo[$modelClass] = Gate::getPolicyFor($modelClass) !== null;
        }

        if (! $this->policyPresenceMemo[$modelClass]) {
            return null;
        }

        $user = Auth::user();
        $argument = $record ?? $modelClass;

        return (bool) Gate::forUser($user)->check($ability, $argument);
    }

    private function shieldCheck(string $modelClass, string $ability): ?bool
    {
        if (! config('lyre.filament-shield', false)) {
            return null;
        }

        if (! function_exists('get_model_permission_by_prefix')) {
            return null;
        }

        $memoKey = $modelClass.'|'.$ability;
        if (array_key_exists($memoKey, $this->shieldPermissionMemo)) {
            $permission = $this->shieldPermissionMemo[$memoKey];
        } else {
            try {
                $permission = get_model_permission_by_prefix($modelClass, $ability);
            } catch (\Throwable) {
                $permission = null;
            }
            $this->shieldPermissionMemo[$memoKey] = is_string($permission) && $permission !== '' ? $permission : null;
            $permission = $this->shieldPermissionMemo[$memoKey];
        }

        if ($permission === null) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        if (! method_exists($user, 'can')) {
            return null;
        }

        return (bool) $user->can($permission);
    }

    private function fallback(): bool
    {
        $mode = (string) config('lyre-filament-admin.authorization.fallback', 'deny');

        return match ($mode) {
            'allow' => true,
            'allow_for_super_admin' => $this->isSuperAdmin(),
            default => false,
        };
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $role = config('lyre-filament-admin.authorization.super_admin_role')
            ?? config('lyre.super-admin', 'super_admin');

        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole($role);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }
}

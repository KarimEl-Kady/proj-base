<?php

namespace App\Modules\Auth\Support;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use InvalidArgumentException;

class UserUuidPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        if (($config['driver'] ?? 'database') !== 'database') {
            throw new InvalidArgumentException('The project password broker requires the database token driver.');
        }

        $key = (string) $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }

        return new UserUuidTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}

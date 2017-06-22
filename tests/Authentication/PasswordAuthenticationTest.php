<?php
namespace Spike\Tests;

use PHPUnit\Framework\TestCase;
use Spike\Authentication\PasswordAuthentication;

class PasswordAuthenticationTest extends TestCase
{
    protected function create($auth = null)
    {
        $auth = $auth ?: [
            'username' => 'foo',
            'password' => 'bar'
        ];
        return new PasswordAuthentication($auth);
    }

    public function testVerify()
    {
        $authenticator = $this->create();
        $this->assertTrue($authenticator->verify([
            'username' => 'foo',
            'password' => 'bar'
        ]));
        $this->assertFalse($authenticator->verify([
            'username' => 'foo',
            'password' => 'baz'
        ]));
        $this->assertFalse($authenticator->verify([
            'username' => 'baz',
            'password' => 'bar'
        ]));
    }

    public function testVerifyWithoutPassword()
    {
        $authenticator = $this->create([
            'username' => 'foo',
        ]);
        $this->assertTrue($authenticator->verify([
            'username' => 'foo',
        ]));
        $this->assertTrue($authenticator->verify([
            'username' => 'foo',
            'password' => 'baz'
        ]));
        $this->assertFalse($authenticator->verify([
            'username' => 'baz',
        ]));
    }
}
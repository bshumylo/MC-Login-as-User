<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\LoginAsUser\Api;

use Espo\Core\Api\Request;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\LoginAsUser\Api\PostStop;
use Espo\Modules\LoginAsUser\Classes\Authentication\Hook\Hooks\LoginAsUser;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCookie;
use PHPUnit\Framework\TestCase;

class PostStopTest extends TestCase
{
    private function request(): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getHeader')->willReturnCallback(
            fn (string $name) => $name === 'X-Requested-With' ? 'XMLHttpRequest' : null
        );

        return $request;
    }

    public function testStopIsLoggedAtWarningLevel(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn('u1');
        $user->method('get')->willReturnCallback(
            fn (string $name) => $name === LoginAsUser::ACTOR_ATTRIBUTE ? 'a1' : null
        );

        $log = $this->createMock(Log::class);
        $log->expects($this->once())
            ->method('warning')
            ->with('Login as User: stopped.', $this->callback(
                fn (array $context) => $context['userId'] === 'a1' && $context['loginAsUserId'] === 'u1'
            ));
        $log->expects($this->never())->method('notice');

        $response = (new PostStop($user, $this->createStub(TicketCookie::class), $log))->process($this->request());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNothingIsLoggedWithoutAnActiveSession(): void
    {
        $log = $this->createMock(Log::class);
        $log->expects($this->never())->method('warning');

        $response = (new PostStop($this->createStub(User::class), $this->createStub(TicketCookie::class), $log))
            ->process($this->request());

        $this->assertSame(200, $response->getStatusCode());
    }
}

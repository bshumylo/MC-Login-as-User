<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\LoginAsUser\Api;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\HeaderKey;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\LoginAsUser\Api\PostStart;
use Espo\Modules\LoginAsUser\Classes\Authentication\Hook\Hooks\LoginAsUser;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TargetPolicy;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCodec;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCookie;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

/**
 * The guards of the "start" endpoint: who may call it and which token it accepts.
 */
class PostStartTest extends TestCase
{
    private const SESSION_TOKEN = 'session-token-1';

    private function request(?string $authorization): Request
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            HeaderKey::AUTHORIZATION => $authorization,
        ];

        $request = $this->createStub(Request::class);
        $request->method('getHeader')->willReturnCallback(fn (string $name) => $headers[$name] ?? null);
        $request->method('getParsedBody')->willReturn((object) ['userId' => 'u1']);

        return $request;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function actor(bool $isAdmin, bool $isApi, array $attributes): User
    {
        $user = $this->createStub(User::class);
        $user->method('isAdmin')->willReturn($isAdmin);
        $user->method('isApi')->willReturn($isApi);
        $user->method('getId')->willReturn('a1');
        $user->method('get')->willReturnCallback(fn (string $name) => $attributes[$name] ?? null);

        return $user;
    }

    private function start(User $actor, Log $log): PostStart
    {
        $target = $this->createStub(User::class);
        $target->method('getId')->willReturn('u1');

        $entityManager = $this->createStub(EntityManager::class);
        $entityManager->method('getEntityById')->willReturn($target);

        $policy = $this->createStub(TargetPolicy::class);
        $policy->method('isAllowed')->willReturn(true);

        $codec = $this->createStub(TicketCodec::class);
        $codec->method('encode')->willReturn('signed-ticket');

        $cookie = $this->createStub(TicketCookie::class);
        $cookie->method('create')->willReturn('ticket-cookie');

        return new PostStart(
            $actor,
            $entityManager,
            $this->createStub(Config::class),
            $codec,
            $cookie,
            $policy,
            $log,
        );
    }

    private function header(string $secondPart): string
    {
        return base64_encode('admin:' . $secondPart);
    }

    public function testApiUserIsRefused(): void
    {
        // An API user must be refused even if another check would let it through.
        $actor = $this->actor(true, true, ['token' => self::SESSION_TOKEN]);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Only administrators can log in as other users.');

        $this->start($actor, $this->createStub(Log::class))->process($this->request($this->header(self::SESSION_TOKEN)));
    }

    public function testRefusedWhileActingAsAnotherUser(): void
    {
        $actor = $this->actor(true, false, [
            'token' => self::SESSION_TOKEN,
            LoginAsUser::ACTOR_ATTRIBUTE => 'a0',
        ]);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Only administrators can log in as other users.');

        $this->start($actor, $this->createStub(Log::class))->process($this->request($this->header(self::SESSION_TOKEN)));
    }

    public function testHeaderValueThatIsNotTheSessionTokenIsRefused(): void
    {
        $actor = $this->actor(true, false, ['token' => self::SESSION_TOKEN]);

        $log = $this->createMock(Log::class);
        $log->expects($this->never())->method('warning');

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Only a session authenticated by an auth token can log in as another user.');

        $this->start($actor, $log)->process($this->request($this->header('a-password')));
    }

    public function testRefusedWhenTheSessionHasNoToken(): void
    {
        $actor = $this->actor(true, false, []);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Only a session authenticated by an auth token can log in as another user.');

        $this->start($actor, $this->createStub(Log::class))->process($this->request($this->header('anything')));
    }

    public function testRefusedWithoutAuthorizationHeader(): void
    {
        $actor = $this->actor(true, false, ['token' => self::SESSION_TOKEN]);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Only a session authenticated by an auth token can log in as another user.');

        $this->start($actor, $this->createStub(Log::class))->process($this->request(null));
    }

    public function testStartsWithTheSessionTokenAndLogsAtWarningLevel(): void
    {
        $actor = $this->actor(true, false, ['token' => self::SESSION_TOKEN]);

        $log = $this->createMock(Log::class);
        $log->expects($this->once())
            ->method('warning')
            ->with('Login as User: started.', $this->callback(
                fn (array $context) => $context['userId'] === 'a1' && $context['loginAsUserId'] === 'u1'
            ));
        $log->expects($this->never())->method('notice');

        $response = $this->start($actor, $log)->process($this->request($this->header(self::SESSION_TOKEN)));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ticket-cookie', $response->getHeader('Set-Cookie'));
    }
}

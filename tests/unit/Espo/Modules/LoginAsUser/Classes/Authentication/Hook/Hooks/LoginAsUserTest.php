<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\LoginAsUser\Classes\Authentication\Hook\Hooks;

use Espo\Core\Api\Request;
use Espo\Core\Authentication\AuthenticationData;
use Espo\Core\Authentication\Result;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\LoginAsUser\Classes\Authentication\Hook\Hooks\LoginAsUser;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TargetPolicy;
use Espo\Modules\LoginAsUser\Classes\Impersonation\Ticket;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCodec;
use Espo\Modules\LoginAsUser\Classes\Impersonation\TicketCookie;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The auth hook: the swap of the logged administrator for the target user.
 */
class LoginAsUserTest extends TestCase
{
    private const TOKEN = 'session-token-1';

    /**
     * A user that keeps its attributes in memory (no database, no metadata).
     *
     * @param array<string, mixed> $attributes
     */
    private function user(array $attributes): User
    {
        return new class ($attributes) extends User {
            /**
             * @param array<string, mixed> $attributes
             */
            public function __construct(private array $attributes)
            {}

            public function getId(): string
            {
                return (string) $this->attributes['id'];
            }

            public function get(string $attribute): mixed
            {
                return $this->attributes[$attribute] ?? null;
            }

            public function has(string $attribute): bool
            {
                return array_key_exists($attribute, $this->attributes);
            }

            public function set($attribute, $value = null): static
            {
                if (is_string($attribute)) {
                    $this->attributes[$attribute] = $value;

                    return $this;
                }

                foreach (get_object_vars((object) $attribute) as $name => $item) {
                    $this->attributes[$name] = $item;
                }

                return $this;
            }

            public function reset(): void
            {
                $this->attributes = [];
            }

            public function getValueMap(): stdClass
            {
                return (object) $this->attributes;
            }

            public function loadLinkMultipleField(string $field, ?array $columns = null): void
            {}
        };
    }

    private function codec(): TicketCodec
    {
        $config = new class extends Config {
            public function __construct()
            {}

            public function get(string $name, $default = null): mixed
            {
                return $name === 'cryptKey' ? 'secret-key' : $default;
            }
        };

        return new TicketCodec($config);
    }

    private function admin(): User
    {
        return $this->user([
            'id' => 'a1',
            'type' => User::TYPE_ADMIN,
            'isActive' => true,
            'ipAddress' => '203.0.113.7',
            'token' => self::TOKEN,
            'authTokenId' => 'auth-token-id',
            'authLogRecordId' => 'auth-log-id',
        ]);
    }

    private function process(User $admin, User $target, ?string $token = self::TOKEN): void
    {
        $codec = $this->codec();

        $ticket = new Ticket('a1', $target->getId(), TicketCodec::hashToken(self::TOKEN), time() + 600);

        $request = $this->createStub(Request::class);
        $request->method('getCookieParam')->willReturnCallback(
            fn (string $name) => $name === TicketCookie::NAME ? $codec->encode($ticket) : null
        );

        $entityManager = $this->createStub(EntityManager::class);
        $entityManager->method('getEntityById')->willReturn($target);

        $hook = new LoginAsUser($admin, $entityManager, $codec, new TargetPolicy(), $this->createStub(Log::class));

        $hook->process(Result::success($admin), AuthenticationData::create()->withPassword($token), $request);
    }

    public function testSwapKeepsTheSessionIdsOfTheAdministratorButNotTheToken(): void
    {
        $admin = $this->admin();
        $target = $this->user(['id' => 'u1', 'type' => User::TYPE_REGULAR, 'isActive' => true, 'userName' => 'user']);

        $this->process($admin, $target);

        $this->assertSame('u1', $admin->getId());
        $this->assertSame('user', $admin->get('userName'));
        $this->assertSame('a1', $admin->get(LoginAsUser::ACTOR_ATTRIBUTE));
        $this->assertSame('203.0.113.7', $admin->get('ipAddress'));
        $this->assertSame('auth-token-id', $admin->get('authTokenId'));
        $this->assertSame('auth-log-id', $admin->get('authLogRecordId'));
        $this->assertNull($admin->get('token'));
    }

    public function testNoSwapForAnAdministratorTarget(): void
    {
        $admin = $this->admin();
        $target = $this->user(['id' => 'b1', 'type' => User::TYPE_ADMIN, 'isActive' => true]);

        $this->process($admin, $target);

        $this->assertSame('a1', $admin->getId());
        $this->assertNull($admin->get(LoginAsUser::ACTOR_ATTRIBUTE));
        $this->assertSame(self::TOKEN, $admin->get('token'));
    }

    public function testNoSwapForAPortalTarget(): void
    {
        $admin = $this->admin();
        $target = $this->user(['id' => 'p1', 'type' => User::TYPE_PORTAL, 'isActive' => true]);

        $this->process($admin, $target);

        $this->assertSame('a1', $admin->getId());
        $this->assertNull($admin->get(LoginAsUser::ACTOR_ATTRIBUTE));
    }

    public function testNoSwapWhenTheTicketBelongsToAnotherSessionToken(): void
    {
        $admin = $this->admin();
        $target = $this->user(['id' => 'u1', 'type' => User::TYPE_REGULAR, 'isActive' => true]);

        $this->process($admin, $target, 'another-token');

        $this->assertSame('a1', $admin->getId());
        $this->assertNull($admin->get(LoginAsUser::ACTOR_ATTRIBUTE));
    }
}

<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The signed-in greeting in the shared public layout.
 *
 * There is a real leak here, and it is recorded in AuditLogger::describeActor()
 * for the audit trail: Shield's User entity resolves `->email` to the identity
 * *secret*, and legacy rows of this app stored the bcrypt password hash in that
 * column. Rendering `$user->email` therefore printed a credential into the page
 * source of every logged-in page - visible in view-source, in shared screenshots,
 * and in anything caching the HTML.
 *
 * user_display_name() is the guard. These tests pin the two properties that
 * matter: a real name wins, and a secret-shaped value can never be returned no
 * matter which column it came from.
 *
 * @internal
 */
final class UserGreetingTest extends CIUnitTestCase
{
    /**
     * Stands in for a Shield User entity whose `email` accessor returns the
     * identity secret rather than an address.
     */
    private function shieldUser(array $attrs): object
    {
        $user = new class {
            public ?string $first_name = null;
            public ?string $last_name = null;
            public ?string $username = null;
            public ?string $email = null;
            public ?string $secret = null;
            public ?string $identityName = null;

            public function getEmail(): ?string
            {
                return $this->email;
            }

            public function getEmailIdentity(): ?object
            {
                if ($this->identityName === null && $this->secret === null) {
                    return null;
                }

                return (object) [
                    'name'   => $this->identityName,
                    'secret' => $this->secret,
                ];
            }
        };

        foreach ($attrs as $key => $value) {
            $user->{$key} = $value;
        }

        return $user;
    }

    private const BCRYPT = '$2y$10$uz7Ttw3eYYALVkHvKxReCAXa3eH.eSyNs8W96XMI4M3dCW/ztui';

    public function testTheRealNameIsUsed(): void
    {
        $name = user_display_name($this->shieldUser([
            'first_name' => 'Mark',
            'last_name'  => 'Loyd',
            'email'      => self::BCRYPT,
        ]));

        $this->assertSame('Mark Loyd', $name);
    }

    public function testASingleGivenNameStillRenders(): void
    {
        $this->assertSame('Mark', user_display_name($this->shieldUser(['first_name' => 'Mark'])));
    }

    /**
     * The regression itself: the account in the report has no usable name column,
     * so resolution falls through to the entity's email accessor - which hands
     * back the password hash.
     */
    public function testAHashInTheEmailColumnIsNeverReturned(): void
    {
        $name = user_display_name($this->shieldUser(['email' => self::BCRYPT]));

        $this->assertStringNotContainsString('$2y$', $name);
        $this->assertStringNotContainsString('uz7Ttw3e', $name);
    }

    public function testAnArgonHashIsAlsoRejected(): void
    {
        $name = user_display_name($this->shieldUser(['email' => '$argon2id$v=19$m=65536,t=3,p=4$abc$def']));

        $this->assertStringNotContainsString('argon', $name);
    }

    /** The same guard has to hold for a hash reached via the identity secret. */
    public function testAHashInTheIdentitySecretIsNotRescuedAsAnEmail(): void
    {
        $name = user_display_name($this->shieldUser([
            'email'      => null,
            'secret'     => self::BCRYPT,
            'identityName' => null,
        ]));

        $this->assertStringNotContainsString('$2y$', $name);
    }

    /** A genuine address is still allowed through once names fail. */
    public function testARealEmailIsUsedAsALastResort(): void
    {
        $name = user_display_name($this->shieldUser(['email' => 'teacher@cscs.edu.ph']));

        $this->assertSame('teacher@cscs.edu.ph', $name);
    }

    public function testUsernameIsPreferredOverEmail(): void
    {
        $name = user_display_name($this->shieldUser([
            'username' => 'mloyd',
            'email'    => 'teacher@cscs.edu.ph',
        ]));

        $this->assertSame('mloyd', $name);
    }

    /** Nothing usable must render as empty, not as a stray comma. */
    public function testNothingUsableReturnsEmptySoTheGreetingDegradesCleanly(): void
    {
        $this->assertSame('', user_display_name($this->shieldUser([])));
        $this->assertSame('', user_display_name(null));
    }

    /**
     * The fallback chain must end somewhere useful. A row with no usable name
     * used to render a bare "Welcome" with nothing after it, which reads as a bug
     * in its own right, so the role label is now the last link.
     */
    public function testARoleLabelCatchesTheRowTheNameColumnsCannot(): void
    {
        $teacher = new class {
            public ?string $first_name = null;
            public ?string $last_name = null;
            public ?string $username = null;
            public ?string $email = null;
            public ?string $secret = null;
            public ?string $identityName = null;

            public function getEmail(): ?string
            {
                return self::BCRYPT_FOR_ROLE;
            }

            public function getEmailIdentity(): ?object
            {
                return (object) ['name' => null, 'secret' => self::BCRYPT_FOR_ROLE];
            }

            public function inGroup(string $group): bool
            {
                return $group === 'teacher';
            }

            private const BCRYPT_FOR_ROLE = '$2y$10$uz7Ttw3eYYALVkHmVxKxReCAXa3eH.eSyNs8W96XMI4M3dCW/ztui';
        };

        $this->assertSame('', user_display_name($teacher), 'the hash must never resolve to a name');
        $this->assertSame('Teacher', user_role_label($teacher));
    }

    public function testUserRoleLabelIsEmptyWithoutAGroup(): void
    {
        $this->assertSame('', user_role_label(null));
        $this->assertSame('', user_role_label($this->shieldUser([])), 'no inGroup() means no label');
    }

    /**
     * The shape test is the whole guard, so it is pinned directly rather than
     * only through user_display_name(). The length floor is the interesting half:
     * it has to catch a hash format PHP does not recognise, while refusing to
     * reject a long but perfectly valid email address.
     */
    public function testTheShapeTestSeparatesCredentialsFromRealValues(): void
    {
        // Known formats.
        $this->assertTrue(user_value_is_secret(self::BCRYPT));
        $this->assertTrue(user_value_is_secret('$argon2id$v=19$m=65536,t=3,p=4$abc$def'));

        // A real password_hash() output, so the check is proven against what this
        // app actually writes rather than a hand-typed string.
        $this->assertTrue(user_value_is_secret(password_hash('correct horse', PASSWORD_DEFAULT)));

        // A long unbroken run PHP does not recognise: the backstop the length
        // floor exists for.
        $this->assertTrue(user_value_is_secret(str_repeat('a1b2c3d4', 5)));

        // Real values must survive, including a long mailbox (254 characters is
        // the legal maximum, and it has no spaces, so without the "@" exemption
        // the length floor would throw it away).
        $this->assertFalse(user_value_is_secret('Mark'));
        $this->assertFalse(user_value_is_secret('teacher@cscs.edu.ph'));
        $this->assertFalse(user_value_is_secret(str_repeat('a', 60) . '@example.com'));
        $this->assertFalse(user_value_is_secret('Maria Concepcion Santos-Bautista'));
        $this->assertFalse(user_value_is_secret(''));
        $this->assertFalse(user_value_is_secret(null));
    }

    public function testTheFallbackIsUsedWhenNothingElseResolves(): void
    {
        $this->assertSame('Teacher', user_display_name($this->shieldUser([]), 'Teacher'));
    }

    /**
     * Guards the call site itself, not just the helper: the layout must not go
     * back to reading $user->email directly.
     */
    public function testTheLayoutDoesNotRenderTheEmailPropertyDirectly(): void
    {
        $layout = (string) file_get_contents(ROOTPATH . 'app/Views/layout.php');

        $this->assertStringNotContainsString(
            'esc($user->email)',
            $layout,
            'Layout renders $user->email again, which is the identity secret on Shield entities.'
        );
    }
}

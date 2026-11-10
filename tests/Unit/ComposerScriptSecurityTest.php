<?php

namespace Tests\Unit;

use Tests\TestCase;

class ComposerScriptSecurityTest extends TestCase
{
    public function test_setup_script_no_regenera_app_key_si_ya_existe(): void
    {
        $composerJson = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
        $setupCommands = $composerJson['scripts']['setup'] ?? [];

        // Verificar que no hay key:generate incondicional
        foreach ($setupCommands as $command) {
            if (is_string($command) && str_contains($command, 'key:generate')) {
                $this->assertStringContainsString('APP_KEY=', $command);
                $this->assertStringContainsString('!preg_match', $command);
            }
        }

        // Probar la lógica de preservación con un .env institucional sintético
        $envConKey = "APP_NAME=FamilyLock\nAPP_KEY=base64:INSTITUTIONAL_KEY_MOCK_1234567890=\nAPP_ENV=production\n";
        $hasKey = (bool) preg_match('/^APP_KEY=.+/m', $envConKey);
        $this->assertTrue($hasKey, 'Debe detectar la presencia de una APP_KEY existente');

        // Probar con un .env nuevo o vacío
        $envSinKey = "APP_NAME=FamilyLock\nAPP_KEY=\nAPP_ENV=production\n";
        $needsKey = ! preg_match('/^APP_KEY=.+/m', $envSinKey);
        $this->assertTrue($needsKey, 'Debe detectar cuándo una APP_KEY está vacía y requiere generación');
    }

    public function test_post_update_no_contiene_comandos_destructivos(): void
    {
        $composerJson = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
        $postUpdate = $composerJson['scripts']['post-update-cmd'] ?? [];

        foreach ($postUpdate as $command) {
            $this->assertStringNotContainsString('key:generate', $command);
            $this->assertStringNotContainsString('migrate:fresh', $command);
            $this->assertStringNotContainsString('db:wipe', $command);
        }
    }
}

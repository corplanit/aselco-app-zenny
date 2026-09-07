<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiContractTest extends TestCase
{
    public function test_meta_and_openapi_are_public(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('version', 'v1')
            ->assertJsonStructure(['name', 'version', 'success_convention', 'error_convention', 'auth']);

        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonStructure(['paths', 'components']);
    }

    public function test_unauthenticated_api_uses_canonical_error_shape(): void
    {
        $this->getJson('/api/v1/auth/user')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['message', 'code']);
    }
}

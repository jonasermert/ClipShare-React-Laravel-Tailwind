<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClipShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_kontoregistrierung_anmeldung_und_abmeldung(): void
    {
        $account = [
            'name' => 'Testkonto',
            'email' => 'TEST@example.com',
            'password' => 'sicheres Testpasswort',
        ];

        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/register', $account)->assertCreated()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonMissingPath('user.password_hash');
        $this->postJson('/api/auth/register', $account)->assertConflict();
        $this->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', [...$account, 'password' => 'falsch'])->assertUnprocessable();
        $this->postJson('/api/auth/login', $account)->assertOk();
    }

    public function test_bestehender_bcryptjs_hash_funktioniert(): void
    {
        $password = 'Altes Passwort!';
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $user = User::create([
            'name' => 'Altes Konto',
            'email' => 'alt@example.com',
            'password_hash' => '$2a$'.substr($hash, 4),
        ]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_aufnahme_freigabe_wiedergabe_und_loeschen(): void
    {
        Storage::fake('uploads');

        $owner = User::create(['name' => 'Besitzer', 'email' => 'a@example.com', 'password_hash' => '']);
        $other = User::create(['name' => 'Anderes Konto', 'email' => 'b@example.com', 'password_hash' => '']);
        $this->actingAs($owner);

        $this->post('/api/videos', [
            'title' => 'Ungültiger Upload',
            'video' => new UploadedFile(__FILE__, 'kein-video.webm', 'video/webm', null, true),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $video = $this->post('/api/videos', [
            'title' => 'Aufnahme',
            'video' => new UploadedFile(base_path('tests/Fixtures/testvideo.webm'), 'aufnahme.webm', null, null, true),
        ], ['Accept' => 'application/json'])->assertCreated()->json('video');

        $id = $video['id'];
        $url = $video['videoUrl'];
        $filename = basename($url);
        Storage::disk('uploads')->assertExists($filename);
        $this->getJson('/api/videos')->assertJsonCount(1, 'videos');
        $this->getJson("/api/videos/$id")->assertJsonPath('video.title', 'Aufnahme');
        $this->patchJson("/api/videos/$id", ['title' => 'Neuer Titel', 'description' => 'Beschreibung'])
            ->assertOk()->assertJsonPath('video.title', 'Neuer Titel');
        $this->patchJson("/api/videos/$id", ['title' => 'Neuer Titel', 'description' => 'Beschreibung'])->assertOk();

        $this->actingAs($other);
        $this->getJson('/api/videos')->assertJsonCount(0, 'videos');
        $this->getJson("/api/videos/$id")->assertNotFound();
        $this->patchJson("/api/videos/$id", ['title' => 'Fremder Zugriff'])->assertNotFound();
        $this->deleteJson("/api/videos/$id")->assertNotFound();

        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson("/api/public/videos/$id")->assertOk()->assertJsonPath('video.title', 'Neuer Titel');
        $this->get($url, ['Range' => 'bytes=0-9'])->assertStatus(206)->assertHeader('Content-Type', 'video/webm');

        $this->actingAs($owner)->deleteJson("/api/videos/$id")->assertOk();
        Storage::disk('uploads')->assertMissing($filename);
        $this->getJson("/api/public/videos/$id")->assertNotFound();
        $this->get($url)->assertNotFound();
    }
}

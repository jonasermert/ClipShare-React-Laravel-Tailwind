# ClipShare mit Laravel und React

ClipShare nimmt Webcam oder Bildschirm im Browser auf. Angemeldete Nutzer können Aufnahmen speichern, abspielen, beschreiben, löschen und per Link weitergeben. Die Mediathek und die Bearbeitung sind an das eigene Konto gebunden. Wer einen Freigabelink kennt, kann das betreffende Video ohne Anmeldung ansehen.

Diese Fassung basiert auf `jonasermert/React-Node-Video-Sharing`, Stand `aaa70736bc959f90d237c02f00261f36aba43b6a`. Laravel 13 übernimmt die bisherigen Aufgaben von Express. React, TypeScript, Vite und das Layout bleiben erhalten. Die Oberfläche, die wichtigsten API-Fehlermeldungen und diese Anleitung sind auf Deutsch. Node.js wird zum Entwickeln und Bauen des Frontends benötigt.

## Voraussetzungen

Verwende PHP ab 8.4.1, Composer 2, Node.js ab 22.13 und npm ab 10. PHP braucht unter anderem PDO, Fileinfo und den passenden PDO-Treiber für SQLite oder MySQL. Die Entwicklungsbefehle unten sind für eine Shell unter macOS oder Linux geschrieben. Unter Windows kannst du sie beispielsweise in WSL ausführen.

Für Webcam und Bildschirmfreigabe brauchst du einen aktuellen Browser, die entsprechenden Berechtigungen und einen sicheren Browser-Kontext. Für die lokale Entwicklung genügt `localhost`. Auf einem bereitgestellten Server verwende HTTPS. Die Verfügbarkeit von Bildschirmton hängt von Browser, Betriebssystem und der ausgewählten Freigabequelle ab.

## Lokal starten

Führe die Befehle im entpackten Projektordner aus. SQLite dient als Voreinstellung, damit kein separater Datenbankserver nötig ist. Wenn bereits eine `.env` oder Datenbank vorhanden ist, behalte diese Dateien und passe nur die benötigten Werte an.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Starte Vite in einem zweiten Terminal im selben Projektordner.

```sh
npm ci
npm run dev
```

Öffne [ClipShare im Vite-Entwicklungsserver](http://localhost:5173). Vite leitet `/api` und `/uploads` an Laravel weiter. Bei einem abweichenden Laravel-Port kopiere `client/.env.example` nach `client/.env.local`, setze `CLIPSHARE_API_TARGET` auf die neue Adresse und starte Vite neu.

Die ZIP enthält auch das gebaute React-Frontend. Sobald Laravel eingerichtet ist, kannst du es ohne laufenden Vite-Prozess unter [ClipShare auf Laravel](http://127.0.0.1:8000) öffnen. Nach Änderungen am Frontend baust du es mit `npm run build` neu. Die Ausgabedateien landen unter `public/spa`.

## Größere Aufnahmen speichern

Die API akzeptiert Videodateien bis 500 MiB. Setze dafür `upload_max_filesize = 500M` und `post_max_size = 510M` in der PHP-Konfiguration des Servers. Stelle außerdem sicher, dass der Webserver oder Reverse-Proxy die entsprechende Anfragegröße zulässt. Wenn du die lokale PHP-Konfiguration nicht bearbeiten möchtest, kannst du Laravel für den Upload-Test so starten. Beende zuvor den Server, der bereits Port 8000 nutzt.

```sh
cd public
php -d upload_max_filesize=500M -d post_max_size=510M \
  -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

Die Dateien liegen unter `storage/app/uploads`. Die API liefert sie über `/uploads/{dateiname}` aus und unterstützt Byte-Bereiche, damit Browser im Video springen können.

## MySQL verwenden

Trage bei Bedarf diese Werte in `.env` ein und ersetze die Beispielpasswörter. Für einen bestehenden MySQL-Server übernimm seine Zugangsdaten und lass die Docker-spezifische Variable `MYSQL_ROOT_PASSWORD` weg.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clipshare
DB_USERNAME=clipshare
DB_PASSWORD=lokales-beispielpasswort-ersetzen
MYSQL_ROOT_PASSWORD=lokales-root-beispielpasswort-ersetzen
```

Wenn du eine neue lokale MySQL-Instanz mit Docker möchtest, starte den enthaltenen Dienst. Warte, bis `docker compose ps` den Dienst als verfügbar meldet, und führe dann die Migrationen aus. Die App läuft weiterhin in PHP auf dem Host.

```sh
docker compose up -d mysql
docker compose ps
php artisan migrate
```

## Bestehende Daten aus der Node-Version übernehmen

Erstelle vor dem Wechsel eine Sicherung der MySQL-Datenbank und des alten Upload-Ordners. Für eine konsistente Kopie halte Schreibzugriffe während der Sicherung an. Nutze für den ersten Test eine Kopie der Datenbank, beispielsweise in einer eigenen MySQL-Datenbank. Falls beide MySQL-Instanzen lokal laufen, verwende unterschiedliche Ports.

Importiere die Sicherung in die vorgesehene Datenbank und richte die MySQL-Verbindung in Laravels `.env` ein. Kopiere die Dateien aus dem alten Upload-Verzeichnis unverändert nach `storage/app/uploads`. Standardmäßig war der Ordner in der Node-Version `server/uploads`. Maßgeblich ist das tatsächlich verwendete Arbeitsverzeichnis des Express-Prozesses.

```sh
mkdir -p storage/app/uploads
cp -R /pfad/zur/node-version/server/uploads/. storage/app/uploads/
php artisan migrate
```

Die Migrationen übernehmen vorhandene `users`- und `videos`-Tabellen und legen Laravels zusätzliche Tabellen an. IDs, Metadaten, Dateinamen und bcrypt-Passworthashes bleiben erhalten. Nutze für diese Tabellen keinen Rollback und kein `migrate:fresh`. Alte JWT-Sitzungen gehen beim Wechsel verloren. Die Nutzer melden sich mit ihrem bisherigen Passwort neu an. Bestehende Linkpfade unter `/view/{id}` und `/uploads/{dateiname}` funktionieren weiter. Wenn sich die öffentliche Domain ändert, richte auf der alten Domain eine Weiterleitung ein, damit bereits verschickte Links erreichbar bleiben.

## API und Betrieb

Die bisherigen JSON-Endpunkte bleiben erhalten. Registrierung, Anmeldung, Abmeldung und Sitzungsabfrage liegen unter `/api/auth/register`, `/api/auth/login`, `/api/auth/logout` und `/api/auth/me`. Video-Liste und Upload verwenden `/api/videos`, Bearbeitung und Löschung `/api/videos/{id}`, die öffentliche Ansicht `/api/public/videos/{id}`.

Die React-Anwendung nutzt eine Laravel-Sitzung über ein HttpOnly-Cookie. Vor schreibenden Anfragen liest sie über `GET /api/auth/csrf` ein Token und schickt es im Header `X-CSRF-TOKEN` mit. Uploads verwenden `multipart/form-data` mit den Feldern `video`, `title` und optional `description`. Laravel erkennt den Dateityp serverseitig.

Richte den Webserver mit `public/` als Dokumentenwurzel ein und leite Anfragen für sonstige Pfade an `public/index.php` weiter. Stelle sicher, dass PHP in `storage/` und `bootstrap/cache/` schreiben kann. Verwende im Betrieb `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` und die öffentliche HTTPS-Adresse in `APP_URL`. Bewahre den einmal erzeugten `APP_KEY` auf. Serviere React, API und Videodateien über denselben Ursprung.

## Tests ausführen

```sh
php artisan test
npm run build
```

Die API-Tests nutzen eine SQLite-Datenbank im Speicher und ein synthetisches WebM. Sie prüfen Kontoerstellung und Sitzungen, Kompatibilität mit einem alten bcrypt-Hash, Dateitypvalidierung, Bearbeitung, Zugriffsschutz, Freigabe, HTTP-Teilantworten und Löschung. Zum Testen der Produktionsausgabe unter `/view/{id}` öffne eine Aufnahme in einem separaten Browserfenster ohne Anmeldung.

import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { auth } from '../store/auth';
import type { Video } from '../types';

export default function VideoEdit() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [video, setVideo] = useState<Video | null>(null);
  const [message, setMessage] = useState('');

  useEffect(() => {
    auth.request<{ video: Video }>(`/api/videos/${id}`).then((data) => setVideo(data.video));
  }, [id]);

  async function save() {
    if (!video) return;
    const data = await auth.request<{ video: Video }>(`/api/videos/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({
        title: video.title,
        description: video.description,
      }),
    });
    setVideo(data.video);
    setMessage('Gespeichert');
  }

  async function remove() {
    if (!confirm('Dieses Video endgültig löschen?')) return;
    await auth.request(`/api/videos/${id}`, { method: 'DELETE' });
    navigate('/videos');
  }

  async function copy() {
    if (!video) return;
    await navigator.clipboard.writeText(video.shareUrl);
    setMessage('Link kopiert');
  }

  if (!video) return null;

  return (
    <section>
      <div className="title-row">
        <h1>Video bearbeiten</h1>
        <button
          className="button danger"
          onClick={remove}
        >
          Löschen
        </button>
      </div>

      <div className="card form wide">
        <video
          src={video.videoUrl}
          controls
        />

        <label>
          Freigabelink
          <div className="inline">
            <input
              value={video.shareUrl}
              readOnly
            />
            <button
              type="button"
              className="button small"
              onClick={copy}
            >
              Kopieren
            </button>
          </div>
        </label>

        <label>
          Titel
          <input
            value={video.title}
            onChange={(e) => setVideo({ ...video, title: e.target.value })}
          />
        </label>

        <label>
          Beschreibung
          <textarea
            value={video.description ?? ''}
            onChange={(e) => setVideo({ ...video, description: e.target.value })}
            rows={4}
          />
        </label>

        <button
          className="button"
          onClick={save}
        >
          Änderungen speichern
        </button>
        <p className="success">{message}</p>
      </div>
    </section>
  );
}

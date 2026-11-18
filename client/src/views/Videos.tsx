import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { auth } from '../store/auth';
import type { Video } from '../types';

export default function Videos() {
  const [videos, setVideos] = useState<Video[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    auth.request<{ videos: Video[] }>('/api/videos').then((data) => {
      setVideos(data.videos);
      setLoading(false);
    });
  }, []);

  return (
    <section>
      <div className="title-row">
        <div>
          <span className="eyebrow">Mediathek</span>
          <h1>Meine Videos</h1>
        </div>
        <Link
          className="button"
          to="/videos/create"
        >
          Neue Aufnahme
        </Link>
      </div>

      {loading ? (
        <p>Wird geladen …</p>
      ) : videos.length ? (
        <div className="grid">
          {videos.map((video) => (
            <Link
              key={video.id}
              to={`/videos/${video.id}`}
              className="video-card"
            >
              <video
                src={video.videoUrl}
                preload="metadata"
              />
              <div>
                <h2>{video.title}</h2>
                <p>{video.description || 'Keine Beschreibung'}</p>
                <small>{new Date(video.createdAt).toLocaleString()}</small>
              </div>
            </Link>
          ))}
        </div>
      ) : (
        <div className="empty">Du hast noch keine Aufnahmen. Nimm dein erstes Video auf.</div>
      )}
    </section>
  );
}

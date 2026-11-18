import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { auth } from '../store/auth';
import type { Video } from '../types';

export default function Capture() {
  const navigate = useNavigate();
  const liveRef = useRef<HTMLVideoElement>(null);
  const previewRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const recorderRef = useRef<MediaRecorder | null>(null);

  const [audio, setAudio] = useState(true);
  const [hasStream, setHasStream] = useState(false);
  const [recording, setRecording] = useState(false);
  const [blob, setBlob] = useState<Blob | null>(null);
  const [error, setError] = useState('');

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [saving, setSaving] = useState(false);

  const canRecord = hasStream && !recording;

  function cleanup() {
    streamRef.current?.getTracks().forEach((t) => t.stop());
  }

  function stop() {
    if (recorderRef.current && recorderRef.current.state !== 'inactive') {
      recorderRef.current.stop();
    }
    setRecording(false);
    streamRef.current?.getTracks().forEach((t) => t.stop());
    streamRef.current = null;
    setHasStream(false);
  }

  async function capture(kind: 'screen' | 'camera') {
    cleanup();
    try {
      const mediaStream =
        kind === 'screen'
          ? await navigator.mediaDevices.getDisplayMedia({ video: true, audio })
          : await navigator.mediaDevices.getUserMedia({ video: true, audio });

      streamRef.current = mediaStream;
      setHasStream(true);
      if (liveRef.current) liveRef.current.srcObject = mediaStream;
      mediaStream.getVideoTracks()[0].addEventListener('ended', stop);
    } catch (e) {
      setError((e as Error).message);
    }
  }

  function start() {
    if (!streamRef.current) return;
    if (typeof MediaRecorder === 'undefined') {
      setError('Dieser Browser unterstützt keine Videoaufnahmen.');
      return;
    }

    const mimeType = ['video/webm;codecs=vp9,opus', 'video/webm', 'video/mp4'].find((type) =>
      MediaRecorder.isTypeSupported(type),
    );
    const chunks: BlobPart[] = [];

    let recorder: MediaRecorder;
    try {
      recorder = new MediaRecorder(streamRef.current, mimeType ? { mimeType } : undefined);
    } catch {
      setError('Die Aufnahme konnte nicht gestartet werden.');
      return;
    }
    recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
    recorder.onstop = () => {
      const recordedBlob = new Blob(chunks, { type: recorder.mimeType });
      if (!recordedBlob.size) {
        setError('Die Aufnahme enthält keine Videodaten.');
        return;
      }
      setBlob(recordedBlob);
      setTitle(`Aufnahme vom ${new Date().toLocaleString()}`);
    };
    recorder.start(500);
    recorderRef.current = recorder;
    setRecording(true);
  }

  async function save(e: FormEvent) {
    e.preventDefault();
    if (!blob) return;
    setSaving(true);
    const f = new FormData();
    f.append('video', blob, blob.type.startsWith('video/mp4') ? 'aufnahme.mp4' : 'aufnahme.webm');
    f.append('title', title);
    f.append('description', description);

    try {
      const data = await auth.request<{ video: Video }>('/api/videos', {
        method: 'POST',
        body: f,
      });
      navigate(`/videos/${data.video.id}`);
    } catch (err) {
      setError((err as Error).message);
    } finally {
      setSaving(false);
    }
  }

  useEffect(() => cleanup, []);

  useEffect(() => {
    if (!blob || !previewRef.current) return;
    const url = URL.createObjectURL(blob);
    previewRef.current.src = url;

    return () => URL.revokeObjectURL(url);
  }, [blob]);

  return (
    <section>
      <div className="title-row">
        <div>
          <span className="eyebrow">Aufnahme</span>
          <h1>Video aufnehmen</h1>
        </div>
      </div>

      <div className="card capture">
        {!blob ? (
          <>
            <video
              ref={liveRef}
              autoPlay
              muted
              playsInline
            />

            {!hasStream ? (
              <div className="capture-actions">
                <button
                  className="button"
                  onClick={() => capture('screen')}
                >
                  Bildschirm teilen
                </button>
                <button
                  className="button secondary"
                  onClick={() => capture('camera')}
                >
                  Webcam verwenden
                </button>
                <label className="check">
                  <input
                    type="checkbox"
                    checked={audio}
                    onChange={(e) => setAudio(e.target.checked)}
                  />
                  Ton aufnehmen
                </label>
              </div>
            ) : (
              <div className="capture-actions">
                {canRecord ? (
                  <button
                    className="button danger"
                    onClick={start}
                  >
                    Aufnahme starten
                  </button>
                ) : (
                  <button
                    className="button danger"
                    onClick={stop}
                  >
                    Aufnahme stoppen
                  </button>
                )}
              </div>
            )}
          </>
        ) : (
          <form onSubmit={save}>
            <video
              ref={previewRef}
              controls
            />
            <label>
              Titel
              <input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
                maxLength={160}
              />
            </label>
            <label>
              Beschreibung
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={4}
                maxLength={2000}
              />
            </label>
            <button
              className="button"
              disabled={saving}
            >
              {saving ? 'Wird gespeichert …' : 'Video speichern'}
            </button>
          </form>
        )}

        {error && <p className="error">{error}</p>}
      </div>
    </section>
  );
}

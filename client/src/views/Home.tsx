import { Link } from 'react-router-dom';

export default function Home() {
  return (
    <section className="hero">
      <div>
        <span className="eyebrow" />
        <h1>Videos aufnehmen und teilen</h1>
        <p>
          Nimm deinen Bildschirm oder deine Webcam direkt im Browser auf, verwalte deine
          Aufnahmen im Konto und teile sie per Link.
        </p>
        <div className="actions">
          <Link
            className="button"
            to="/videos/create"
          >
            Aufnahme starten
          </Link>
          <Link
            className="button secondary"
            to="/register"
          >
            Konto erstellen
          </Link>
        </div>
      </div>
      <div className="preview">
        <div className="camera">● REC</div>
        <div className="screen">
          <div className="play">▶</div>
        </div>
      </div>
    </section>
  );
}

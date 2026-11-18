import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { auth, useAuth } from './store/auth';
import AppRoutes from './router';

export default function App() {
  const { user, ready } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (!ready) auth.load();
  }, [ready]);

  async function logout() {
    await auth.logout();
    navigate('/');
  }

  if (!ready) return null;

  return (
    <div className="app">
      <header>
        <Link
          className="brand"
          to="/"
        >
          ClipShare
        </Link>
        <nav>
          {user ? (
            <>
              <Link to="/videos">Meine Videos</Link>
              <Link to="/videos/create">Aufnehmen</Link>
              <button
                className="link"
                onClick={logout}
              >
                Abmelden
              </button>
            </>
          ) : (
            <>
              <Link to="/login">Anmelden</Link>
              <Link
                className="button small"
                to="/register"
              >
                Registrieren
              </Link>
            </>
          )}
        </nav>
      </header>
      <main>
        <AppRoutes />
      </main>
    </div>
  );
}

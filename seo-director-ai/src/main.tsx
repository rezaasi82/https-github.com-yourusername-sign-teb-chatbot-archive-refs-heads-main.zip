import { createRoot } from 'react-dom/client';
import { App } from './app/App';
import './styles/tokens.css';

const mount = document.getElementById('sda-root');

if (mount) {
  mount.querySelector('.sda-boot-splash')?.remove();
  createRoot(mount).render(<App />);
}

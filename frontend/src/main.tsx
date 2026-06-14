import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/base.css';
import './runtime/posUiEnhancements';

document.title = 'RootLabs POS';

const container = document.getElementById('mx-pos-pro-root');

if (container) {
  const root = createRoot(container);
  root.render(
    <StrictMode>
      <App />
    </StrictMode>
  );
}

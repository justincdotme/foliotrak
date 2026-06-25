import { createRoot } from 'react-dom/client'
import App from './components/App'
import '../css/app.css'

const root = document.getElementById('app')
if (!root) {
  throw new Error('Root element not found')
}

createRoot(root).render(<App />)

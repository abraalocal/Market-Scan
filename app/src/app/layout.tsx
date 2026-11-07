import type { Metadata, Viewport } from 'next';
import './globals.css';
import { AppProvider } from '@/lib/app-context';
import { Toast } from '@/components/ui';

export const metadata: Metadata = {
  title: 'Market Scan',
  description: 'Photograph shop shelves and business cards; suppliers and products go to abraa.com.',
  manifest: '/manifest.webmanifest',
  appleWebApp: { capable: true, title: 'Market Scan', statusBarStyle: 'default' },
  icons: { icon: '/icon.svg', apple: '/icon.svg' },
};

export const viewport: Viewport = { width: 'device-width', initialScale: 1, viewportFit: 'cover', themeColor: '#2563eb' };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" dir="ltr">
      <body><AppProvider>{children}<Toast /></AppProvider></body>
    </html>
  );
}

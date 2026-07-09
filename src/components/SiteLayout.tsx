import { Header } from "@/components/Header";
import { Footer } from "@/components/Footer";
import { ChatWidget } from "@/components/ChatWidget";

/**
 * Layout compartido del sitio público.
 * Usado por app/(site)/layout.tsx — las páginas nuevas bajo (site)/ lo heredan solas.
 */
export function SiteLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <Header />
      <main className="flex-1">{children}</main>
      <Footer />
      <ChatWidget />
    </>
  );
}

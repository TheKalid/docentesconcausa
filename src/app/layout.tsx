import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { AuthProvider } from "@/components/AuthProvider";

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "Herramientas para Docentes | Docentes con causa",
  description:
    "Tu herramienta aliada para una planeación didáctica inteligente, creativa y con propósito social. Generada por IA y alineada a la NEM.",
  icons: {
    icon: [{ url: "/logocel.png", type: "image/png" }],
    apple: "/logocel.png",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es" className={`${inter.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-sans">
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}

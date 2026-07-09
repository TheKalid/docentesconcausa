/**
 * Layout para páginas de autenticación (sin Header ni Footer).
 * Rutas: /login, /registro
 */
export default function AuthRouteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <>{children}</>;
}

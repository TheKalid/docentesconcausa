import { SiteLayout } from "@/components/SiteLayout";

export default function SiteRouteLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <SiteLayout>{children}</SiteLayout>;
}

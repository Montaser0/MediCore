import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";

export const metadata: Metadata = {
  title: "MediCore",
  description: "MediCore",
  icons: { icon: "/logo.png?v=3" },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html
      lang="ar-u-nu-latn"
      dir="rtl"
      className="h-full"
      style={
        {
          "--font-arabic": "Tahoma, Arial, sans-serif",
        } as React.CSSProperties
      }
    >
      <body className="min-h-full font-sans antialiased">
        <Script src="/hms-config.js" strategy="beforeInteractive" />
        {children}
      </body>
    </html>
  );
}

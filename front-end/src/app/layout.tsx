import type { Metadata } from "next";
import { Noto_Sans_Arabic } from "next/font/google";
import Script from "next/script";
import "./globals.css";

const arabic = Noto_Sans_Arabic({
  variable: "--font-arabic",
  subsets: ["arabic"],
  weight: ["400", "500", "600", "700"],
});

export const metadata: Metadata = {
  title: "MediCore",
  description: "MediCore",
  icons: { icon: "/logo.png?v=3" },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar-u-nu-latn" dir="rtl" className={`${arabic.variable} h-full`}>
      <body className="min-h-full font-sans antialiased">
        <Script src="/hms-config.js" strategy="beforeInteractive" />
        {children}
      </body>
    </html>
  );
}

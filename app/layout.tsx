import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = { title: "PELIŞ — Duru Bir Etki", description: "Modern, zamansız ve kendin gibi hissettiren Pelış seçkisi.", icons: { icon: "https://cdn.lavira360.com/pelish/logo.png" } };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { return <html lang="tr"><body>{children}</body></html>; }

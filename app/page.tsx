"use client";

import { useEffect, useState } from "react";

type Product = { name: string; category: string; price: string; old?: string; image: string; tone: string; badge?: string };

const products: Product[] = [
  { name: "Luna Saten Elbise", category: "Yeni Sezon", price: "₺2.490", old: "₺2.990", tone: "#d9c6bb", badge: "Yeni", image: "https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=900&q=85" },
  { name: "Élan Keten Takım", category: "Zamansız Parçalar", price: "₺3.250", tone: "#cfcec7", image: "https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=900&q=85" },
  { name: "Muse Drapeli Bluz", category: "Yeni Sezon", price: "₺1.390", tone: "#c8b5ae", badge: "Sınırlı", image: "https://images.unsplash.com/photo-1485968579580-b6d095142e6e?auto=format&fit=crop&w=900&q=85" },
  { name: "Isla Midi Etek", category: "Zamansız Parçalar", price: "₺1.790", tone: "#d7d0c8", image: "https://images.unsplash.com/photo-1572804013427-4d7ca7268217?auto=format&fit=crop&w=900&q=85" },
  { name: "Nora İnce Triko", category: "Yeni Sezon", price: "₺1.690", tone: "#c2b7a8", image: "https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=900&q=85" },
  { name: "Soleil Şehir Çantası", category: "Aksesuar", price: "₺2.150", tone: "#bba99c", badge: "Editörün Seçimi", image: "https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=85" },
];

const Icons = {
  bag: <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>,
  heart: <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.9-8.6a5.5 5.5 0 0 0-.1-7.8Z"/></svg>,
  search: <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.2 4.2"/></svg>,
  arrow: <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6"/></svg>,
  close: <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>,
};

export default function Home() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [welcomeOpen, setWelcomeOpen] = useState(true);
  const [searchOpen, setSearchOpen] = useState(false);
  const [cart, setCart] = useState(0);
  const [liked, setLiked] = useState<string[]>([]);
  const [productOpen, setProductOpen] = useState<Product | null>(null);
  const [activeTab, setActiveTab] = useState("Tümü");
  const [toast, setToast] = useState("");

  useEffect(() => {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => entry.isIntersecting && entry.target.classList.add("is-visible")), { threshold: 0.14 });
    document.querySelectorAll(".reveal").forEach((node) => observer.observe(node));
    return () => observer.disconnect();
  }, []);

  useEffect(() => { if (toast) { const id = window.setTimeout(() => setToast(""), 2600); return () => window.clearTimeout(id); } }, [toast]);
  useEffect(() => { document.body.style.overflow = (menuOpen || welcomeOpen || productOpen || searchOpen) ? "hidden" : ""; }, [menuOpen, welcomeOpen, productOpen, searchOpen]);

  const addCart = (p: Product) => { setCart((x) => x + 1); setToast(`${p.name} sepetine eklendi.`); setProductOpen(null); };
  const filtered = activeTab === "Tümü" ? products : products.filter((p) => p.category === activeTab);

  return <main>
    <div className="announcement"><div className="announcement__track"><span>Türkiye’nin her yerine ücretsiz kargo</span><i>✦</i><span>Yeni sezon seçkisi şimdi yayında</span><i>✦</i><span>İlk alışverişe özel %10 hoş geldin indirimi</span><i>✦</i><span>Türkiye’nin her yerine ücretsiz kargo</span></div></div>
    <header className="header">
      <button className="menu-toggle" onClick={() => setMenuOpen(true)} aria-label="Menüyü aç"><span></span><span></span><em>Menü</em></button>
      <a className="logo" href="#top" aria-label="Pelish ana sayfa"><img src="https://cdn.lavira360.com/pelish/logo.png" alt="Pelish" /></a>
      <nav className="desktop-nav"><a href="#new">Yeni Sezon</a><a href="#collections">Koleksiyonlar</a><a href="#story">Hikâyemiz</a></nav>
      <div className="header-actions"><button onClick={() => setSearchOpen(true)} aria-label="Ara">{Icons.search}</button><button className="fav-button" onClick={() => setToast("Favorilerin kaydedildi.")} aria-label="Favoriler">{Icons.heart}<small>{liked.length || ""}</small></button><button className="cart-button" onClick={() => setToast(cart ? `${cart} ürün sepetinde.` : "Sepetin henüz boş.")} aria-label="Sepet">{Icons.bag}<small>{cart || ""}</small></button></div>
    </header>

    <section className="hero" id="top"><div className="hero__image"></div><div className="hero__veil"></div><div className="hero__copy"><p className="eyebrow light">SONBAHAR / KIŞ 26</p><h1>Sessizce<br/><i>etki</i> bırak.</h1><p className="hero__sub">Duru silüetler, dokunmak isteyeceğin dokular ve senin ritmine eşlik eden parçalar.</p><a href="#new" className="button button--light">Koleksiyonu keşfet {Icons.arrow}</a></div><div className="hero__number">01 <span>/ 03</span></div><button className="hero__scroll" onClick={() => document.getElementById("new")?.scrollIntoView({ behavior: "smooth" })}>Kaydır <span></span></button></section>

    <section className="intro section reveal"><p className="eyebrow">PELIŞ İLE TANIŞ</p><h2>Giyinmekten öte,<br/>kendin gibi <i>hissetmek.</i></h2><p>Her koleksiyonumuz, güçlü bir sadelikten doğar. Günün her anına hafifçe eşlik eden, iyi hissettiren parçalar tasarlıyoruz.</p><a className="text-link" href="#story">Hikâyemizi oku {Icons.arrow}</a></section>

    <section className="collections section" id="collections"><div className="section-heading reveal"><p className="eyebrow">SEÇKİLER</p><h2>Bir ruh hâli seç.</h2><a className="text-link" href="#new">Tümünü gör {Icons.arrow}</a></div><div className="collection-grid"><a className="collection-card collection-card--large reveal" href="#new"><img src="https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=1200&q=85" alt="Günlük şıklık"/><span><em>01</em><strong>Şehrin Ritmi</strong><b>Keşfet {Icons.arrow}</b></span></a><a className="collection-card reveal" href="#new"><img src="https://images.unsplash.com/photo-1539008835657-9e8e9680c956?auto=format&fit=crop&w=900&q=85" alt="Akşam şıklığı"/><span><em>02</em><strong>Geceye Doğru</strong><b>Keşfet {Icons.arrow}</b></span></a><a className="collection-card reveal" href="#new"><img src="https://images.unsplash.com/photo-1496217590455-aa63a8350eea?auto=format&fit=crop&w=900&q=85" alt="Zamansız parçalar"/><span><em>03</em><strong>Zamansız</strong><b>Keşfet {Icons.arrow}</b></span></a></div></section>

    <section className="products section" id="new"><div className="section-heading reveal"><div><p className="eyebrow">YENİ GELENLER</p><h2>Şimdi senin için seçtik.</h2></div><div className="tabs">{["Tümü", "Yeni Sezon", "Zamansız Parçalar", "Aksesuar"].map((tab) => <button key={tab} onClick={() => setActiveTab(tab)} className={activeTab === tab ? "active" : ""}>{tab}</button>)}</div></div><div className="product-grid">{filtered.map((p, idx) => <article className="product-card reveal" style={{ transitionDelay: `${idx * 55}ms` }} key={p.name}><div className="product-card__visual" style={{ background: p.tone }}><img src={p.image} alt={p.name}/>{p.badge && <span className="product-badge">{p.badge}</span>}<div className="product-card__actions"><button onClick={() => setLiked((all) => all.includes(p.name) ? all.filter((x) => x !== p.name) : [...all, p.name])} className={liked.includes(p.name) ? "liked" : ""} aria-label="Favorilere ekle">{Icons.heart}</button><button onClick={() => setProductOpen(p)}>Hızlı İncele</button></div></div><div className="product-card__info"><div><p>{p.category}</p><h3>{p.name}</h3></div><div className="price">{p.old && <del>{p.old}</del>}<strong>{p.price}</strong></div></div></article>)}</div><div className="center reveal"><button className="button button--dark" onClick={() => setToast("Ürünler sayfası yakında daha fazla seçkiyle güncellenecek.")}>Tüm ürünleri gör {Icons.arrow}</button></div></section>

    <section className="editorial" id="story"><div className="editorial__image reveal"><img src="https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1200&q=85" alt="Pelish editoryal koleksiyon"/></div><div className="editorial__copy reveal"><p className="eyebrow">PELIŞ JOURNAL</p><h2>İyi bir parça,<br/>iyi bir <i>hikâye</i> taşır.</h2><p>Atölyeden şehre, tasarımın arkasındaki küçük ama anlamlı ayrıntıları keşfet.</p><a className="text-link" href="#top">Journal’a git {Icons.arrow}</a></div></section>
    <section className="newsletter section reveal"><p className="eyebrow">PELIŞ’E YAKIN OL</p><h2>Güzel haberler<br/>posta kutunda.</h2><form onSubmit={(e) => { e.preventDefault(); setToast("Teşekkürler, listeye eklendin."); }}><input aria-label="E-posta adresiniz" required type="email" placeholder="E-posta adresin"/><button aria-label="Abone ol">{Icons.arrow}</button></form><small>Abone olarak gizlilik politikamızı kabul etmiş olursun.</small></section>
    <footer><div className="footer-top"><img src="https://cdn.lavira360.com/pelish/logo.png" alt="Pelish"/><p>Modern romantiklerin<br/>günlük üniforması.</p><div className="socials"><a href="#top">Instagram</a><a href="#top">Pinterest</a><a href="#top">TikTok</a></div></div><div className="footer-bottom"><span>© 2026 PELIŞ</span><span>İstanbul, Türkiye</span><span>KVKK · Mesafeli Satış</span></div></footer>

    {welcomeOpen && <div className="overlay welcome"><div className="welcome__card"><button className="modal-close" onClick={() => setWelcomeOpen(false)} aria-label="Kapat">{Icons.close}</button><div className="welcome__art"><span>✦</span><span>✦</span><span>✦</span></div><p className="eyebrow">YENİ BİR BAŞLANGIÇ</p><h2>Yeni adresimize<br/><i>hoş geldin.</i></h2><p>Yeni dünyamızı keşfederken ilk alışverişine özel <b>PELIŞ10</b> kodu ile %10 indirim seninle.</p><button className="button button--dark" onClick={() => setWelcomeOpen(false)}>Keşfe başla {Icons.arrow}</button></div></div>}
    {menuOpen && <div className="menu-layer"><button className="modal-close menu-close" onClick={() => setMenuOpen(false)} aria-label="Menüyü kapat">{Icons.close}</button><div className="menu-layer__brand">PELIŞ<sup>®</sup></div><nav>{["Yeni Sezon", "Elbiseler", "Üst Giyim", "Alt Giyim", "Dış Giyim", "Aksesuar"].map((item, i) => <a key={item} href="#new" onClick={() => setMenuOpen(false)}><span>0{i + 1}</span>{item}<b>{Icons.arrow}</b></a>)}</nav><div className="menu-layer__bottom"><p>Yeni sezondan haberdar ol.</p><a href="#top">@pelish.official</a></div></div>}
    {searchOpen && <div className="overlay search-overlay"><div className="search-box"><button className="modal-close" onClick={() => setSearchOpen(false)} aria-label="Kapat">{Icons.close}</button><p className="eyebrow">ARAMAYA BAŞLA</p><div>{Icons.search}<input autoFocus placeholder="Ne arıyorsun?" onKeyDown={(e) => e.key === "Enter" && (setSearchOpen(false), setToast("Arama sonuçların hazırlanıyor."))}/></div><p className="search-hint">Popüler: Elbise · Takım · Triko · Çanta</p></div></div>}
    {productOpen && <div className="overlay quickview"><div className="quickview__card"><button className="modal-close" onClick={() => setProductOpen(null)} aria-label="Kapat">{Icons.close}</button><img src={productOpen.image} alt={productOpen.name}/><div><p className="eyebrow">{productOpen.category}</p><h2>{productOpen.name}</h2><strong>{productOpen.price}</strong><p>Zamansız silüeti, yumuşak dokusu ve kusursuz kalıbıyla her anına eşlik edecek.</p><div className="sizes"><button>S</button><button>M</button><button>L</button></div><button className="button button--dark" onClick={() => addCart(productOpen)}>Sepete ekle {Icons.bag}</button></div></div></div>}
    {toast && <div className="toast">{toast}<button onClick={() => setToast("")}>{Icons.close}</button></div>}
  </main>;
}

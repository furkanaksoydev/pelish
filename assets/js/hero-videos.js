(() => {
  const slides = [...document.querySelectorAll('.hero-image')];
  if (!slides.length) return;

  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;
  const activeVideo = (slide) => slide?.querySelector(isMobile() ? '.mobile-video' : '.desktop-video');
  const syncVideos = () => {
    const activeSlide = document.querySelector('.hero-image.active');
    document.querySelectorAll('.hero-video').forEach(video => { if (video !== activeVideo(activeSlide)) video.pause(); });
    const video = activeVideo(activeSlide);
    if (video && !document.hidden) video.play().catch(() => {});
  };

  new MutationObserver(syncVideos).observe(document.querySelector('.hero-slides'), { subtree: true, attributes: true, attributeFilter: ['class'] });
  window.addEventListener('resize', syncVideos, { passive: true });
  document.addEventListener('visibilitychange', syncVideos);
  syncVideos();
})();

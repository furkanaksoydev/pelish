<?php

declare(strict_types=1);

require __DIR__ . '/store/layout.php';

$company = store_company($config);
$key = (string) ($_GET['belge'] ?? 'gizlilik-politikasi');
if ($key === 'gizlilik-cerez') { $key = 'gizlilik-politikasi'; }
if ($key === 'iade') { $key = 'teslimat-iade'; }

$seller = [
    'unvan' => (string) ($company['legal_name'] ?? ''),
    'adres' => (string) ($company['address'] ?? ''),
    'telefon' => (string) ($company['phone'] ?? ''),
    'eposta' => (string) ($company['email'] ?? ''),
    'mersis' => (string) ($company['mersis_no'] ?? ''),
    'vergi' => trim((string) ($company['tax_office'] ?? '') . ' / ' . (string) ($company['tax_no'] ?? ''), ' /'),
    'kep' => (string) ($company['kep_address'] ?? ''),
];
$value = static fn (string $key): string => trim($seller[$key]) !== '' ? $seller[$key] : 'Bilgi sipariş ve iletişim kayıtlarında satıcı tarafından ayrıca bildirilir.';
$address = static fn (string $text): string => nl2br(e($text));

$privacySections = [
    ['1. Veri sorumlusu', 'Bu Gizlilik Politikası kapsamında veri sorumlusu, aşağıdaki unvan ve iletişim bilgileri yer alan satıcıdır. Pelish, üyelik ve satış işlemlerinin yürütülmesi sırasında kişisel verileri 6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) ve ilgili mevzuata uygun olarak işler.'],
    ['2. İşlenen kişisel veri kategorileri', 'Kimlik ve iletişim verileri (ad, soyad, e-posta, telefon); üyelik ve işlem güvenliği verileri; teslimat adresi; sipariş, sepet, favori, ödeme durumu ve müşteri talebi verileri; ayrıca yalnızca gerekli olduğu ölçüde cihaz, oturum ve zorunlu çerez verileri işlenebilir. Kart numarası, son kullanma tarihi ve CVV, Pelish veritabanında tutulmaz.'],
    ['3. İşleme amaçları ve hukuki sebepler', 'Veriler; üyelik hesabının kurulması, sözleşmenin kurulması ve ifası, ürünün hazırlanması ve teslimatı, müşteri destek hizmeti, iade/cayma sürecinin yürütülmesi, muhasebe ve yasal yükümlülüklerin yerine getirilmesi, bilgi güvenliği ve sahteciliğin önlenmesi amaçlarıyla işlenir. İşleme; sözleşmenin kurulması veya ifası, hukuki yükümlülük, meşru menfaat ve gerektiğinde açık rıza hukuki sebeplerine dayanır.'],
    ['4. Aktarım yapılan taraflar', 'Kişisel veriler yalnızca işlem için gerekli ölçüde; kargo ve lojistik firmalarına, yetkili ödeme kuruluşlarına, bilgi teknolojileri ve barındırma hizmet sağlayıcılarına, mali müşavirlik/hukuk hizmeti sağlayıcılarına ve kanunen yetkili kamu kurumlarına aktarılabilir.'],
    ['5. Toplama yöntemi, saklama ve güvenlik', 'Veriler; üyelik, iletişim, adres, sepet ve sipariş formları ile zorunlu teknik kayıtlar üzerinden elektronik ortamda toplanır. Yasal saklama süreleri ve işlem amacı sona erdiğinde veriler silinir, yok edilir veya anonim hâle getirilir. Pelish; erişim kontrolü, parola güvenliği, yetkilendirme ve kayıt güvenliği için makul teknik ve idari önlemler uygular.'],
    ['6. Çerezler ve ticari iletişim', 'Zorunlu çerezler sitenin oturum, sepet ve güvenlik işlevleri için kullanılır. Analiz veya pazarlama amaçlı çerezler açık tercih olmadan çalıştırılmaz. Kampanya ve duyuru iletişimi isteğe bağlıdır; izin verildiğinde kayıtlar İYS yükümlülükleri gözetilerek tutulur ve bu izin her zaman geri alınabilir.'],
    ['7. İlgili kişinin hakları', 'KVKK’nın 11. maddesi kapsamındaki bilgi alma, düzeltme, silme/yok etme, aktarımları öğrenme, işlemeye itiraz etme ve zarar hâlinde giderim talep etme hakların için aşağıdaki satıcı iletişim kanallarından başvurabilirsin.'],
];

$deliverySections = [
    ['Teslimat', 'Siparişe ilişkin ürün, adet, toplam bedel, teslimat adresi, kargo bedeli ve ödeme yöntemi sipariş onayından önce gösterilir. Sözleşme konusu ürün, stok durumu ve teslimat adresinin uzaklığı dikkate alınarak ön bilgilendirmede açıklanan süre içinde ve her hâlde ilgili mevzuatta öngörülen azami süre aşılmadan alıcıya teslim edilir. Kargo tesliminde paket hasarı görülürse kargo görevlisiyle tutanak tutulması önerilir.'],
    ['İptal, cayma ve iade', 'Tüketici, mevzuattaki istisnalar saklı kalmak üzere malın kendisine veya belirlediği üçüncü kişiye tesliminden itibaren 14 gün içinde, gerekçe göstermeksizin ve cezai şart ödemeksizin cayma hakkını kullanabilir. Cayma bildirimi satıcının aşağıdaki iletişim adresine kalıcı veri saklayıcısı yoluyla iletilmelidir. İade süreci, ürünün ve bildirimin ulaşmasından sonra yürürlükteki mevzuata göre tamamlanır.'],
    ['İade incelemesi', 'Kullanılmış, yıkanmış, etiketi koparılmış veya kozmetik/makyaj lekesi bulunan ürünler yeniden satışa uygunluk bakımından incelenir. Hijyen, kişiye özel üretim ve mevzuatta sayılan diğer istisnalar bakımından cayma hakkının kullanılabilirliği yürürlükteki kurallara göre değerlendirilir; bu açıklama kanundan doğan emredici tüketici haklarını sınırlamaz.'],
];

$contractSections = [
    ['1. Taraflar', 'İşbu Mesafeli Satış Sözleşmesi; bir tarafta aşağıdaki bilgileri yer alan SATICI ile diğer tarafta Pelish internet sitesi üzerinden ürün/hizmet siparişi veren, sipariş aşamasında adı, soyadı, e-posta adresi, telefon numarası ve teslimat adresi alınan ALICI arasında kurulmuştur. Kamuya açık bu metin genel sözleşme metnidir; siparişe özel nüshada taraf, ürün, bedel ve teslimat bilgileri otomatik olarak yer alır.'],
    ['2. Tanımlar', '“SİTE”, Pelish internet sitesini; “SATICI”, ticari veya mesleki faaliyeti kapsamında tüketiciye mal sunan tarafı; “ALICI/ÜYE”, ticari veya mesleki olmayan amaçlarla mal veya hizmet edinen kişiyi; “MAL”, alışverişe konu taşınır ürünü; “KANUN”, 6502 sayılı Tüketicinin Korunması Hakkında Kanunu; “YÖNETMELİK”, Mesafeli Sözleşmeler Yönetmeliğini; “TARAFLAR” ise SATICI ve ALICI’yı ifade eder.'],
    ['3. Sözleşmenin konusu', 'Sözleşmenin konusu; ALICI’nın SİTE üzerinden elektronik ortamda sipariş verdiği ürünlerin nitelikleri, satış fiyatı, ödeme biçimi, teslimatı, cayma hakkı ile TARAFLAR’ın hak ve yükümlülüklerinin Kanun ve Yönetmelik hükümlerine uygun olarak belirlenmesidir.'],
    ['4. Satıcı bilgileri', 'Satıcının unvanı, açık adresi, telefon, e-posta, vergi/MERSİS ve varsa KEP bilgileri aşağıda yer alır. Siparişe özel sözleşme kopyasında bu bilgiler, alıcı ve teslimat bilgileri ile birlikte ayrıca gösterilir.'],
    ['5. Ürün, fiyat ve ödeme bilgileri', 'Ürünün temel nitelikleri, türü, rengi, bedeni, adedi, vergiler dâhil birim ve toplam satış fiyatı, indirim tutarı, kargo bedeli ve ödeme yöntemi, sipariş onayından önce ALICI’ya sunulur. Sitede ilan edilen fiyatlar güncellendiği tarihe kadar geçerlidir; süreli kampanyalar ilan edilen süre boyunca uygulanır.'],
    ['6. Teslimat', 'Sipariş, ALICI’nın onayladığı teslimat adresine teslim edilir. Teslimatın kim tarafından ve hangi süre içinde yapılacağı ön bilgilendirmede belirtilir. SATICI, sipariş konusu ürünü yasal azami teslim süresini aşmamak kaydıyla teslim etmekle yükümlüdür.'],
    ['7. Cayma hakkı', 'ALICI, mevzuattaki istisnalar saklı kalmak üzere ürünün tesliminden itibaren 14 gün içinde SATICI’ya açık bir bildirim yaparak hiçbir gerekçe göstermeden cayma hakkını kullanabilir. Cayma hakkına ilişkin bildirim, SATICI’nın aşağıdaki e-posta/adres iletişim bilgileri üzerinden iletilebilir.'],
    ['8. Cayma hakkının istisnaları', 'ALICI’nın isteği veya kişisel ihtiyaçları doğrultusunda hazırlanan ürünler ile sağlık veya hijyen bakımından iadeye elverişli olmayan ve teslimden sonra koruyucu unsurları açılmış ürünler ve ilgili mevzuatta sayılan diğer ürün/hizmetler için cayma hakkı kullanılamayabilir. Her somut durum yürürlükteki mevzuata göre değerlendirilir.'],
    ['9. Temerrüt ve mücbir sebep', 'ALICI’nın kredi kartı ödemesinde temerrüde düşmesi hâlinde kart sahibi banka ile arasındaki sözleşme hükümleri uygulanır. Tarafların kontrolü dışında gelişen ve makul önlemlerle önlenemeyen mücbir sebep hâllerinde teslimat süresi etkilenirse SATICI, durumu ALICI’ya bildirir ve mevzuata uygun çözüm seçeneklerini sunar.'],
    ['10. Uyuşmazlıkların çözümü ve yürürlük', 'Tüketici uyuşmazlıklarında, yürürlükteki mevzuat uyarınca yetkili tüketici hakem heyetleri ve tüketici mahkemeleri yetkilidir. ALICI, siparişi elektronik ortamda onayladığında; ön bilgilendirme formunu ve işbu sözleşmeyi okuyup kabul ettiğini beyan eder.'],
];

$documents = [
    'gizlilik-politikasi' => ['Gizlilik Politikası', 'Kişisel verilerinin hangi amaçlarla işlendiğini, kimlerle paylaşılabileceğini ve haklarını açıklar.', $privacySections],
    'kvkk' => ['KVKK Aydınlatma Metni', 'KVKK kapsamındaki veri işleme faaliyetlerine ilişkin aydınlatma metnidir.', $privacySections],
    'teslimat-iade' => ['Teslimat ve İade', 'Sipariş, teslimat, cayma ve iade süreçlerindeki temel kurallar.', $deliverySections],
    'mesafeli-satis' => ['Mesafeli Satış Sözleşmesi', 'Taraflar, satış konusu ürün, ödeme, teslimat, cayma hakkı ve uyuşmazlık çözümüne ilişkin genel sözleşme metni.', $contractSections],
    'on-bilgilendirme' => ['Ön Bilgilendirme Formu', 'Ürün, bedel, teslimat, ödeme ve cayma hakkına ilişkin sipariş öncesi temel bilgilendirme.', [['Siparişe özel bilgiler', 'Ödeme adımında sepetindeki ürünler, adetler, fiyatlar, indirim, kargo bedeli, teslimat adresi ve ödeme yöntemi gösterilir.'], ['Belge saklama', 'Onaylanan ön bilgilendirme formunun siparişe özel, zaman damgalı kopyası sipariş kaydıyla birlikte saklanır.']]],
];
$document = $documents[$key] ?? $documents['gizlilik-politikasi'];
store_render_head($document[0]);
store_render_header($pdo);
?>
<main class="store-main legal-main">
  <section class="legal-document">
    <p class="eyebrow">PELISH · YASAL BİLGİLENDİRME</p>
    <h1><?= e($document[0]) ?></h1>
    <p class="legal-updated">Son güncelleme: 26 Ağustos 2026</p>
    <div class="legal-copy">
      <p><?= e($document[1]) ?></p>
      <?php if ($key === 'mesafeli-satis'): ?>
        <section class="legal-party-grid"><div><span>SATICI</span><strong><?= e($value('unvan')) ?></strong><small><?= $address($value('adres')) ?><br><?= e($value('telefon')) ?><br><?= e($value('eposta')) ?></small></div><div><span>ALICI / ÜYE</span><strong>Sipariş veren kişi</strong><small>Ad, soyad, e-posta, telefon ve teslimat adresi; sipariş aşamasında ALICI tarafından girilir ve siparişe özel sözleşme nüshasında yer alır.</small></div><div><span>SİTE</span><strong>www.pelish.co</strong><small>Mesafeli satış işleminin kurulduğu elektronik ortam.</small></div></section>
      <?php endif; ?>
      <?php foreach ($document[2] as [$title, $copy]): ?><section><h2><?= e($title) ?></h2><p><?= e($copy) ?></p></section><?php endforeach; ?>
      <h2>Satıcı / veri sorumlusu iletişim bilgileri</h2>
      <dl><dt>Ticari unvan</dt><dd><?= e($value('unvan')) ?></dd><dt>Açık adres</dt><dd><?= $address($value('adres')) ?></dd><dt>Telefon</dt><dd><?= e($value('telefon')) ?></dd><dt>E-posta</dt><dd><?= e($value('eposta')) ?></dd><dt>Vergi dairesi / no</dt><dd><?= e($value('vergi')) ?></dd><dt>MERSİS No</dt><dd><?= e($value('mersis')) ?></dd><dt>KEP adresi</dt><dd><?= e($value('kep')) ?></dd></dl>
    </div>
    <nav class="legal-nav" aria-label="Yasal metinler"><a href="yasal.php?belge=gizlilik-politikasi">Gizlilik Politikası</a><a href="yasal.php?belge=mesafeli-satis">Mesafeli Satış Sözleşmesi</a><a href="yasal.php?belge=teslimat-iade">Teslimat ve İade</a><a href="yasal.php?belge=on-bilgilendirme">Ön Bilgilendirme</a><a href="yasal.php?belge=kvkk">KVKK</a></nav>
  </section>
</main>
<?php store_render_footer(); ?>

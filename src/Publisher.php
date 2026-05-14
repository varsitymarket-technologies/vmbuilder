<?php
class Publisher {
    private PDO $pdo;
    private SiteManager $siteManager;

    public function __construct(PDO $pdo, SiteManager $siteManager) {
        $this->pdo = $pdo;
        $this->siteManager = $siteManager;
    }

    public function publish(int $siteId): string {
        $site = $this->siteManager->getSite($siteId);
        if (!$site) throw new RuntimeException('Site not found');

        $pages = $this->siteManager->listPages($siteId);
        if (empty($pages)) throw new RuntimeException('Site has no pages');

        $settings = json_decode($site['settings'], true) ?: [];
        $outputDir = PUBLISHED_DIR . '/' . $site['slug'];

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $navItems = [];
        foreach ($pages as $p) {
            $navItems[] = [
                'name' => $p['name'],
                'slug' => $p['slug'],
                'href' => $p['sort_order'] === 0 ? 'index.html' : $p['slug'] . '.html',
            ];
        }

        foreach ($pages as $index => $page) {
            $components = json_decode($page['components'], true) ?: [];
            $seo = json_decode($page['seo'], true) ?: [];
            $filename = $index === 0 ? 'index.html' : $page['slug'] . '.html';

            $bodyHtml = '';
            foreach ($components as $component) {
                $bodyHtml .= $this->renderComponent($component, $navItems, $settings) . "\n";
            }

            $html = $this->wrapHtml($bodyHtml, $seo, $settings, $site['name']);
            file_put_contents($outputDir . '/' . $filename, $html);
        }

        return $site['slug'];
    }

    private function wrapHtml(string $body, array $seo, array $settings, string $siteName): string {
        $title = htmlspecialchars($seo['title'] ?? $siteName, ENT_QUOTES);
        $description = htmlspecialchars($seo['description'] ?? '', ENT_QUOTES);
        $keywords = htmlspecialchars($seo['keywords'] ?? '', ENT_QUOTES);
        $favicon = htmlspecialchars($settings['favicon'] ?? '', ENT_QUOTES);
        $faviconTag = $favicon ? "<link rel=\"icon\" href=\"{$favicon}\">" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <meta name="keywords" content="{$keywords}">
    {$faviconTag}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .lightbox-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center; cursor:pointer; }
        .lightbox-overlay.active { display:flex; }
        .lightbox-overlay img { max-width:90vw; max-height:90vh; object-fit:contain; }
    </style>
</head>
<body class="bg-white text-gray-900">
{$body}
<div class="lightbox-overlay" onclick="this.classList.remove('active')"><img src="" alt=""></div>
<script>
document.querySelectorAll('[data-lightbox]').forEach(function(img){
    img.style.cursor='pointer';
    img.addEventListener('click',function(){
        var overlay=document.querySelector('.lightbox-overlay');
        overlay.querySelector('img').src=this.src;
        overlay.classList.add('active');
    });
});
</script>
</body>
</html>
HTML;
    }

    public function renderComponent(array $component, array $navItems = [], array $siteSettings = []): string {
        $type = $component['type'];
        $props = $component['props'] ?? [];
        $method = 'render' . str_replace('_', '', ucwords($type, '_'));
        if (method_exists($this, $method)) {
            $html = $this->$method($props, $navItems, $siteSettings);
            $twClasses = $this->getTwClasses($props);
            
            // For simple string replace / wrap where appropriate
            if ($twClasses) {
                // If the html starts with `<div ` or `<section ` we could inject it,
                // but just wrapping it is safest for complex ones if we don't rewrite them all.
                // However, matching app.js, we should let components inject it.
            }
            return $html;
        }
        return "<!-- Unknown component: {$type} -->";
    }

    private function getTwClasses(array $props): string {
        $tw = $props['_tw'] ?? [];
        $classArr = [];
        foreach ($tw as $k => $v) {
            if ($v) $classArr[] = $v;
        }
        $custom = $props['classes'] ?? '';
        if ($custom) $classArr[] = $custom;
        return implode(' ', $classArr);
    }

    private function renderNavbar(array $p, array $navItems): string {
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#ffffff');
        $tc = htmlspecialchars($p['textColor'] ?? '#111827');
        $logoText = htmlspecialchars($p['logoText'] ?? 'My Site');
        $logo = $p['logo'] ?? '';
        $sticky = ($p['sticky'] ?? true) ? 'sticky top-0 z-50' : '';
        $logoHtml = $logo
            ? '<img src="' . htmlspecialchars($logo) . '" alt="' . $logoText . '" class="h-8">'
            : '<span class="text-xl font-bold">' . $logoText . '</span>';
        $links = '';
        foreach ($navItems as $item) {
            $links .= '<a href="' . htmlspecialchars($item['href']) . '" class="hover:opacity-75">' . htmlspecialchars($item['name']) . '</a> ';
        }
        return <<<HTML
<nav class="{$sticky} shadow-sm" style="background-color:{$bg};color:{$tc}">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <div>{$logoHtml}</div>
        <div class="flex gap-6 text-sm font-medium">{$links}</div>
    </div>
</nav>
HTML;
    }

    private function renderHero(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $sub = htmlspecialchars($p['subheading'] ?? '');
        $ctaText = htmlspecialchars($p['ctaText'] ?? '');
        $ctaUrl = htmlspecialchars($p['ctaUrl'] ?? '#');
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#1e3a5f');
        $tc = htmlspecialchars($p['textColor'] ?? '#ffffff');
        $bgImg = $p['backgroundImage'] ?? '';
        $overlay = ($p['overlay'] ?? true) ? '<div class="absolute inset-0 bg-black/50"></div>' : '';
        $bgStyle = $bgImg ? "background-image:url('" . htmlspecialchars($bgImg) . "');background-size:cover;background-position:center;" : "background-color:{$bg};";
        $cta = $ctaText ? '<a href="' . $ctaUrl . '" class="inline-block mt-6 px-8 py-3 bg-white text-gray-900 font-semibold rounded-lg hover:bg-gray-100 transition">' . $ctaText . '</a>' : '';
        return <<<HTML
<section class="relative min-h-[500px] flex items-center justify-center text-center" style="{$bgStyle}color:{$tc}">
    {$overlay}
    <div class="relative z-10 max-w-3xl mx-auto px-4">
        <h1 class="text-5xl font-bold mb-4">{$heading}</h1>
        <p class="text-xl opacity-90">{$sub}</p>
        {$cta}
    </div>
</section>
HTML;
    }

    private function renderHeading(array $p): string {
        $twClasses = $this->getTwClasses($p);
        $text = htmlspecialchars($p['text'] ?? '');
        $level = $p['level'] ?? 'h2';
        $align = $p['alignment'] ?? 'left';
        $color = htmlspecialchars($p['color'] ?? '#111827');
        $sizes = ['h1'=>'text-4xl','h2'=>'text-3xl','h3'=>'text-2xl','h4'=>'text-xl','h5'=>'text-lg','h6'=>'text-base'];
        $size = $sizes[$level] ?? 'text-3xl';
        $styleAttr = !str_contains($twClasses, 'text-') ? " style=\"color:{$color}\"" : "";
        return "<div class=\"{$twClasses} text-{$align}\"><{$level} class=\"{$size} font-bold\"{$styleAttr}>{$text}</{$level}></div>";
    }

    private function renderText(array $p): string {
        $twClasses = $this->getTwClasses($p);
        $content = $p['content'] ?? '';
        $align = $p['alignment'] ?? 'left';
        return "<div class=\"{$twClasses} text-{$align} prose prose-lg\">{$content}</div>";
    }

    private function renderImage(array $p): string {
        $src = htmlspecialchars($p['src'] ?? '');
        $alt = htmlspecialchars($p['alt'] ?? '');
        $link = $p['link'] ?? '';
        $widths = ['small'=>'max-w-sm','medium'=>'max-w-lg','large'=>'max-w-4xl','full'=>'max-w-full'];
        $w = $widths[$p['width'] ?? 'full'] ?? 'max-w-full';
        $img = "<img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full h-auto rounded-lg\">";
        if ($link) $img = '<a href="' . htmlspecialchars($link) . '">' . $img . '</a>';
        return "<div class=\"{$w} mx-auto px-4 py-4\">{$img}</div>";
    }

    private function renderVideo(array $p): string {
        $url = $p['url'] ?? '';
        $embedUrl = $this->getEmbedUrl($url);
        $ratios = ['16:9'=>'aspect-video','4:3'=>'aspect-[4/3]','1:1'=>'aspect-square'];
        $ratio = $ratios[$p['aspectRatio'] ?? '16:9'] ?? 'aspect-video';
        return "<div class=\"max-w-4xl mx-auto px-4 py-4\"><div class=\"{$ratio}\"><iframe src=\"{$embedUrl}\" class=\"w-full h-full rounded-lg\" frameborder=\"0\" allowfullscreen></iframe></div></div>";
    }

    private function getEmbedUrl(string $url): string {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . htmlspecialchars($m[1]);
        }
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . htmlspecialchars($m[1]);
        }
        return htmlspecialchars($url);
    }

    private function renderButton(array $p): string {
        $twClasses = $this->getTwClasses($p);
        $text = htmlspecialchars($p['text'] ?? 'Click');
        $link = htmlspecialchars($p['link'] ?? '#');
        $color = htmlspecialchars($p['color'] ?? '#3b82f6');
        $align = $p['alignment'] ?? 'left';
        $style = $p['style'] ?? 'solid';
        $btnClass = $style === 'outline'
            ? "border-2 bg-transparent hover:opacity-75"
            : "text-white hover:opacity-90";
        $btnStyle = $style === 'outline'
            ? "border-color:{$color};color:{$color}"
            : "background-color:{$color}";
        return "<div class=\"text-{$align} {$twClasses}\"><a href=\"{$link}\" class=\"inline-block px-6 py-3 rounded-lg font-semibold transition {$btnClass}\" style=\"{$btnStyle}\">{$text}</a></div>";
    }

    private function renderSection(array $p): string {
        $twClasses = $this->getTwClasses($p);
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#ffffff');
        $bgImg = $p['backgroundImage'] ?? '';
        
        $style = "";
        if (!str_contains($twClasses, 'bg-')) {
             $style .= "background-color:{$bg};";
        }
        if ($bgImg) {
             $style .= "background-image:url('{$bgImg}');background-size:cover;background-position:center;";
        }
        
        if (!str_contains($twClasses, 'p') && !str_contains($twClasses, 'py') && !str_contains($twClasses, 'pt')) {
             $style .= "padding-top:" . (int)($p['paddingTop'] ?? 60) . "px;";
        }
        if (!str_contains($twClasses, 'p') && !str_contains($twClasses, 'py') && !str_contains($twClasses, 'pb')) {
             $style .= "padding-bottom:" . (int)($p['paddingBottom'] ?? 60) . "px;";
        }

        $childHtml = '';
        foreach (($p['children'] ?? []) as $child) {
            $childHtml .= $this->renderComponent($child);
        }
        return "<section class=\"{$twClasses}\" style=\"{$style}\">{$childHtml}</section>";
    }

    private function renderColumns(array $p): string {
        $count = (int)($p['count'] ?? 2);
        $gap = (int)($p['gap'] ?? 24);
        $gridCols = ['2'=>'grid-cols-2','3'=>'grid-cols-3','4'=>'grid-cols-4'];
        $grid = $gridCols[(string)$count] ?? 'grid-cols-2';
        $childHtml = '';
        foreach (($p['children'] ?? []) as $child) {
            $childHtml .= '<div>' . $this->renderComponent($child) . '</div>';
        }
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 grid {$grid}\" style=\"gap:{$gap}px\">{$childHtml}</div>";
    }

    private function renderSpacer(array $p): string {
        $h = (int)($p['height'] ?? 40);
        return "<div style=\"height:{$h}px\"></div>";
    }

    private function renderFeatures(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $cols = (int)($p['columns'] ?? 3);
        $gridCols = ['3'=>'md:grid-cols-3','4'=>'md:grid-cols-4'];
        $grid = $gridCols[(string)$cols] ?? 'md:grid-cols-3';
        $items = '';
        foreach (($p['items'] ?? []) as $item) {
            $icon = htmlspecialchars($item['icon'] ?? '');
            $title = htmlspecialchars($item['title'] ?? '');
            $desc = htmlspecialchars($item['description'] ?? '');
            $items .= "<div class=\"text-center p-6\"><div class=\"text-4xl mb-4\">{$icon}</div><h3 class=\"text-xl font-semibold mb-2\">{$title}</h3><p class=\"text-gray-600\">{$desc}</p></div>";
        }
        return "<section class=\"py-16 bg-gray-50\"><div class=\"max-w-6xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid {$grid} gap-8\">{$items}</div></div></section>";
    }

    private function renderTestimonials(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $items = '';
        foreach (($p['items'] ?? []) as $item) {
            $quote = htmlspecialchars($item['quote'] ?? '');
            $name = htmlspecialchars($item['name'] ?? '');
            $role = htmlspecialchars($item['role'] ?? '');
            $photo = $item['photo'] ?? '';
            $photoHtml = $photo
                ? '<img src="' . htmlspecialchars($photo) . '" class="w-12 h-12 rounded-full object-cover" alt="' . $name . '">'
                : '<div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">' . mb_substr($name,0,1) . '</div>';
            $items .= "<div class=\"bg-white p-6 rounded-xl shadow-sm\"><p class=\"text-gray-600 italic mb-4\">\"{$quote}\"</p><div class=\"flex items-center gap-3\">{$photoHtml}<div><div class=\"font-semibold\">{$name}</div><div class=\"text-sm text-gray-500\">{$role}</div></div></div></div>";
        }
        return "<section class=\"py-16\"><div class=\"max-w-6xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid md:grid-cols-2 gap-8\">{$items}</div></div></section>";
    }

    private function renderPricing(array $p): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $plans = '';
        foreach (($p['plans'] ?? []) as $plan) {
            $name = htmlspecialchars($plan['name'] ?? '');
            $price = htmlspecialchars($plan['price'] ?? '');
            $ctaText = htmlspecialchars($plan['ctaText'] ?? 'Choose');
            $ctaUrl = htmlspecialchars($plan['ctaUrl'] ?? '#');
            $hl = ($plan['highlighted'] ?? false);
            $border = $hl ? 'border-blue-500 border-2 scale-105' : 'border-gray-200 border';
            $btn = $hl ? 'bg-blue-500 text-white' : 'bg-gray-900 text-white';
            $features = is_array($plan['features'] ?? null) ? $plan['features'] : explode("\n", $plan['features'] ?? '');
            $featureHtml = '';
            foreach ($features as $f) {
                $f = trim(htmlspecialchars($f));
                if ($f) $featureHtml .= "<li class=\"py-2 border-b border-gray-100\">{$f}</li>";
            }
            $plans .= "<div class=\"{$border} rounded-2xl p-8 bg-white\"><h3 class=\"text-xl font-bold mb-2\">{$name}</h3><div class=\"text-3xl font-bold mb-6\">{$price}</div><ul class=\"mb-8 text-gray-600\">{$featureHtml}</ul><a href=\"{$ctaUrl}\" class=\"block text-center py-3 rounded-lg font-semibold {$btn} hover:opacity-90 transition\">{$ctaText}</a></div>";
        }
        return "<section class=\"py-16 bg-gray-50\"><div class=\"max-w-5xl mx-auto px-4\"><h2 class=\"text-3xl font-bold text-center mb-12\">{$heading}</h2><div class=\"grid md:grid-cols-3 gap-8 items-start\">{$plans}</div></div></section>";
    }

    private function renderContactForm(array $p, array $navItems = [], array $siteSettings = []): string {
        $heading = htmlspecialchars($p['heading'] ?? '');
        $submitText = htmlspecialchars($p['submitText'] ?? 'Send');
        $successMsg = htmlspecialchars($p['successMessage'] ?? 'Thank you!');
        $fields = '';
        foreach (($p['fields'] ?? []) as $field) {
            $fname = htmlspecialchars($field['name'] ?? '');
            $flabel = htmlspecialchars($field['label'] ?? '');
            $ftype = $field['type'] ?? 'text';
            $req = ($field['required'] ?? false) ? 'required' : '';
            if ($ftype === 'textarea') {
                $fields .= "<div class=\"mb-4\"><label class=\"block text-sm font-medium mb-1\">{$flabel}</label><textarea name=\"{$fname}\" rows=\"4\" class=\"w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent\" {$req}></textarea></div>";
            } else {
                $fields .= "<div class=\"mb-4\"><label class=\"block text-sm font-medium mb-1\">{$flabel}</label><input type=\"{$ftype}\" name=\"{$fname}\" class=\"w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent\" {$req}></div>";
            }
        }
        return <<<HTML
<section class="py-16">
    <div class="max-w-xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-8">{$heading}</h2>
        <form class="contact-form" onsubmit="return handleFormSubmit(this, '{$successMsg}')">
            {$fields}
            <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg font-semibold hover:bg-blue-600 transition">{$submitText}</button>
            <div class="form-success hidden mt-4 p-4 bg-green-50 text-green-700 rounded-lg text-center"></div>
        </form>
    </div>
</section>
<script>
function handleFormSubmit(form, msg) {
    var data = new FormData(form);
    fetch(form.action || window.location.origin + '/api.php?action=form_submit', {
        method: 'POST', body: data
    }).then(function(){
        var el = form.querySelector('.form-success');
        el.textContent = msg;
        el.classList.remove('hidden');
        form.reset();
    });
    return false;
}
</script>
HTML;
    }

    private function renderMap(array $p): string {
        $address = urlencode($p['address'] ?? '');
        $h = (int)($p['height'] ?? 400);
        return "<div class=\"max-w-6xl mx-auto px-4 py-4\"><iframe src=\"https://maps.google.com/maps?q={$address}&output=embed\" width=\"100%\" height=\"{$h}\" style=\"border:0;border-radius:0.75rem\" allowfullscreen loading=\"lazy\"></iframe></div>";
    }

    private function renderGallery(array $p): string {
        $cols = (int)($p['columns'] ?? 3);
        $gap = (int)($p['gap'] ?? 8);
        $gridCols = ['2'=>'grid-cols-2','3'=>'grid-cols-3','4'=>'grid-cols-4'];
        $grid = $gridCols[(string)$cols] ?? 'grid-cols-3';
        $images = '';
        foreach (($p['images'] ?? []) as $img) {
            $src = htmlspecialchars($img['src'] ?? '');
            $alt = htmlspecialchars($img['alt'] ?? '');
            $images .= "<img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full h-64 object-cover rounded-lg\" data-lightbox>";
        }
        return "<div class=\"max-w-6xl mx-auto px-4 py-4 grid {$grid}\" style=\"gap:{$gap}px\">{$images}</div>";
    }

    private function renderFooter(array $p): string {
        $text = htmlspecialchars($p['text'] ?? '');
        $bg = htmlspecialchars($p['backgroundColor'] ?? '#111827');
        $tc = htmlspecialchars($p['textColor'] ?? '#9ca3af');
        $links = '';
        foreach (($p['links'] ?? []) as $link) {
            $links .= '<a href="' . htmlspecialchars($link['url'] ?? '#') . '" class="hover:underline">' . htmlspecialchars($link['label'] ?? '') . '</a> ';
        }
        $socials = '';
        $socialIcons = ['facebook'=>'FB','twitter'=>'X','instagram'=>'IG','linkedin'=>'LI','youtube'=>'YT'];
        foreach (($p['socialLinks'] ?? []) as $sl) {
            $platform = $sl['platform'] ?? '';
            $icon = $socialIcons[$platform] ?? strtoupper(substr($platform,0,2));
            $socials .= '<a href="' . htmlspecialchars($sl['url'] ?? '#') . '" class="hover:opacity-75">' . $icon . '</a> ';
        }
        return <<<HTML
<footer style="background-color:{$bg};color:{$tc}">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-sm">{$text}</div>
            <div class="flex gap-4 text-sm">{$links}</div>
            <div class="flex gap-3 font-bold">{$socials}</div>
        </div>
    </div>
</footer>
HTML;
    }
}

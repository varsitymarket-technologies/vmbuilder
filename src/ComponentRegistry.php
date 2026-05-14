<?php
class ComponentRegistry {
    public static function getAll(): array {
        return [
            'layout' => [
                'section' => self::section(),
                'columns' => self::columns(),
                'spacer' => self::spacer(),
            ],
            'content' => [
                'heading' => self::heading(),
                'text' => self::text(),
                'image' => self::image(),
                'video' => self::video(),
                'button' => self::button(),
            ],
            'business' => [
                'hero' => self::hero(),
                'features' => self::features(),
                'testimonials' => self::testimonials(),
                'pricing' => self::pricing(),
                'contact_form' => self::contactForm(),
                'map' => self::map(),
                'gallery' => self::gallery(),
            ],
            'global' => [
                'navbar' => self::navbar(),
                'footer' => self::footer(),
                'theme_section' => self::themeSection(),
            ],
        ];
    }

    public static function getDefaults(string $type): array {
        $all = self::getAll();
        foreach ($all as $components) {
            if (isset($components[$type])) {
                return $components[$type]['defaults'];
            }
        }
        return [];
    }

    public static function getSchema(string $type): array {
        $all = self::getAll();
        foreach ($all as $components) {
            if (isset($components[$type])) {
                return $components[$type]['schema'];
            }
        }
        return [];
    }

    public static function getFlat(): array {
        $flat = [];
        foreach (self::getAll() as $category => $components) {
            foreach ($components as $type => $def) {
                $flat[$type] = array_merge($def, ['category' => $category]);
            }
        }
        return $flat;
    }

    private static function section(): array {
        return [
            'label' => 'Section',
            'icon' => 'square',
            'defaults' => [
                'backgroundColor' => '#ffffff',
                'backgroundImage' => '',
                'paddingTop' => 60,
                'paddingBottom' => 60,
                'children' => [],
            ],
            'schema' => [
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'backgroundImage', 'type' => 'image', 'label' => 'Background Image'],
                ['key' => 'paddingTop', 'type' => 'number', 'label' => 'Padding Top (px)'],
                ['key' => 'paddingBottom', 'type' => 'number', 'label' => 'Padding Bottom (px)'],
            ],
        ];
    }

    private static function columns(): array {
        return [
            'label' => 'Columns',
            'icon' => 'columns',
            'defaults' => [
                'count' => 2,
                'gap' => 24,
                'children' => [],
            ],
            'schema' => [
                ['key' => 'count', 'type' => 'select', 'label' => 'Columns', 'options' => [2, 3, 4]],
                ['key' => 'gap', 'type' => 'number', 'label' => 'Gap (px)'],
            ],
        ];
    }

    private static function spacer(): array {
        return [
            'label' => 'Spacer',
            'icon' => 'minus',
            'defaults' => ['height' => 40],
            'schema' => [
                ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)'],
            ],
        ];
    }

    private static function heading(): array {
        return [
            'label' => 'Heading',
            'icon' => 'type',
            'defaults' => [
                'text' => 'Heading Text',
                'level' => 'h2',
                'alignment' => 'left',
                'color' => '#111827',
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                ['key' => 'level', 'type' => 'select', 'label' => 'Level', 'options' => ['h1','h2','h3','h4','h5','h6']],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
                ['key' => 'color', 'type' => 'color', 'label' => 'Color'],
            ],
        ];
    }

    private static function text(): array {
        return [
            'label' => 'Text',
            'icon' => 'align-left',
            'defaults' => [
                'content' => '<p>Enter your text here.</p>',
                'alignment' => 'left',
            ],
            'schema' => [
                ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
            ],
        ];
    }

    private static function image(): array {
        return [
            'label' => 'Image',
            'icon' => 'image',
            'defaults' => [
                'src' => '',
                'alt' => '',
                'width' => 'full',
                'link' => '',
            ],
            'schema' => [
                ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
                ['key' => 'alt', 'type' => 'text', 'label' => 'Alt Text'],
                ['key' => 'width', 'type' => 'select', 'label' => 'Width', 'options' => ['small','medium','large','full']],
                ['key' => 'link', 'type' => 'text', 'label' => 'Link URL'],
            ],
        ];
    }

    private static function video(): array {
        return [
            'label' => 'Video',
            'icon' => 'play',
            'defaults' => [
                'url' => '',
                'aspectRatio' => '16:9',
            ],
            'schema' => [
                ['key' => 'url', 'type' => 'text', 'label' => 'YouTube/Vimeo URL'],
                ['key' => 'aspectRatio', 'type' => 'select', 'label' => 'Aspect Ratio', 'options' => ['16:9','4:3','1:1']],
            ],
        ];
    }

    private static function button(): array {
        return [
            'label' => 'Button',
            'icon' => 'mouse-pointer',
            'defaults' => [
                'text' => 'Click Me',
                'link' => '#',
                'style' => 'solid',
                'color' => '#3b82f6',
                'alignment' => 'left',
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Button Text'],
                ['key' => 'link', 'type' => 'text', 'label' => 'Link URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'options' => ['solid','outline']],
                ['key' => 'color', 'type' => 'color', 'label' => 'Color'],
                ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'options' => ['left','center','right']],
            ],
        ];
    }

    private static function hero(): array {
        return [
            'label' => 'Hero',
            'icon' => 'star',
            'defaults' => [
                'heading' => 'Welcome to Our Website',
                'subheading' => 'We help you build something amazing',
                'ctaText' => 'Get Started',
                'ctaUrl' => '#',
                'backgroundImage' => '',
                'backgroundColor' => '#1e3a5f',
                'textColor' => '#ffffff',
                'overlay' => true,
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'subheading', 'type' => 'text', 'label' => 'Subheading'],
                ['key' => 'ctaText', 'type' => 'text', 'label' => 'Button Text'],
                ['key' => 'ctaUrl', 'type' => 'text', 'label' => 'Button URL'],
                ['key' => 'backgroundImage', 'type' => 'image', 'label' => 'Background Image'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'overlay', 'type' => 'toggle', 'label' => 'Dark Overlay'],
            ],
        ];
    }

    private static function features(): array {
        return [
            'label' => 'Features',
            'icon' => 'grid',
            'defaults' => [
                'heading' => 'Our Features',
                'columns' => 3,
                'items' => [
                    ['icon' => '⚡', 'title' => 'Fast', 'description' => 'Lightning fast performance'],
                    ['icon' => '🔒', 'title' => 'Secure', 'description' => 'Built with security in mind'],
                    ['icon' => '📱', 'title' => 'Responsive', 'description' => 'Works on all devices'],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => [3, 4]],
                ['key' => 'items', 'type' => 'repeater', 'label' => 'Features', 'fields' => [
                    ['key' => 'icon', 'type' => 'text', 'label' => 'Icon (emoji)'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                ]],
            ],
        ];
    }

    private static function testimonials(): array {
        return [
            'label' => 'Testimonials',
            'icon' => 'message-circle',
            'defaults' => [
                'heading' => 'What Our Clients Say',
                'items' => [
                    ['quote' => 'Amazing service!', 'name' => 'John Doe', 'role' => 'CEO', 'photo' => ''],
                    ['quote' => 'Highly recommended.', 'name' => 'Jane Smith', 'role' => 'Designer', 'photo' => ''],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'items', 'type' => 'repeater', 'label' => 'Testimonials', 'fields' => [
                    ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote'],
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                    ['key' => 'role', 'type' => 'text', 'label' => 'Role'],
                    ['key' => 'photo', 'type' => 'image', 'label' => 'Photo'],
                ]],
            ],
        ];
    }

    private static function pricing(): array {
        return [
            'label' => 'Pricing',
            'icon' => 'dollar-sign',
            'defaults' => [
                'heading' => 'Pricing Plans',
                'plans' => [
                    ['name' => 'Basic', 'price' => '$9/mo', 'features' => ["5 Pages", "Basic Support", "1GB Storage"], 'highlighted' => false, 'ctaText' => 'Choose Plan', 'ctaUrl' => '#'],
                    ['name' => 'Pro', 'price' => '$29/mo', 'features' => ["Unlimited Pages", "Priority Support", "10GB Storage"], 'highlighted' => true, 'ctaText' => 'Choose Plan', 'ctaUrl' => '#'],
                    ['name' => 'Enterprise', 'price' => '$99/mo', 'features' => ["Everything in Pro", "Dedicated Support", "100GB Storage"], 'highlighted' => false, 'ctaText' => 'Contact Us', 'ctaUrl' => '#'],
                ],
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Section Heading'],
                ['key' => 'plans', 'type' => 'repeater', 'label' => 'Plans', 'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Plan Name'],
                    ['key' => 'price', 'type' => 'text', 'label' => 'Price'],
                    ['key' => 'features', 'type' => 'textarea', 'label' => 'Features (one per line)'],
                    ['key' => 'highlighted', 'type' => 'toggle', 'label' => 'Highlight'],
                    ['key' => 'ctaText', 'type' => 'text', 'label' => 'Button Text'],
                    ['key' => 'ctaUrl', 'type' => 'text', 'label' => 'Button URL'],
                ]],
            ],
        ];
    }

    private static function contactForm(): array {
        return [
            'label' => 'Contact Form',
            'icon' => 'mail',
            'defaults' => [
                'heading' => 'Get In Touch',
                'fields' => [
                    ['name' => 'name', 'label' => 'Your Name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
                    ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
                ],
                'submitText' => 'Send Message',
                'successMessage' => 'Thank you! We will get back to you soon.',
            ],
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'fields', 'type' => 'repeater', 'label' => 'Form Fields', 'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Field Name'],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => ['text','email','tel','textarea']],
                    ['key' => 'required', 'type' => 'toggle', 'label' => 'Required'],
                ]],
                ['key' => 'submitText', 'type' => 'text', 'label' => 'Submit Button Text'],
                ['key' => 'successMessage', 'type' => 'text', 'label' => 'Success Message'],
            ],
        ];
    }

    private static function map(): array {
        return [
            'label' => 'Map',
            'icon' => 'map-pin',
            'defaults' => [
                'address' => '1600 Amphitheatre Parkway, Mountain View, CA',
                'height' => 400,
            ],
            'schema' => [
                ['key' => 'address', 'type' => 'text', 'label' => 'Address'],
                ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)'],
            ],
        ];
    }

    private static function gallery(): array {
        return [
            'label' => 'Gallery',
            'icon' => 'layout',
            'defaults' => [
                'columns' => 3,
                'gap' => 8,
                'images' => [],
            ],
            'schema' => [
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => [2, 3, 4]],
                ['key' => 'gap', 'type' => 'number', 'label' => 'Gap (px)'],
                ['key' => 'images', 'type' => 'repeater', 'label' => 'Images', 'fields' => [
                    ['key' => 'src', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'Alt Text'],
                ]],
            ],
        ];
    }

    private static function navbar(): array {
        return [
            'label' => 'Navbar',
            'icon' => 'menu',
            'defaults' => [
                'logo' => '',
                'logoText' => 'My Site',
                'backgroundColor' => '#ffffff',
                'textColor' => '#111827',
                'sticky' => true,
            ],
            'schema' => [
                ['key' => 'logo', 'type' => 'image', 'label' => 'Logo Image'],
                ['key' => 'logoText', 'type' => 'text', 'label' => 'Logo Text (fallback)'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'sticky', 'type' => 'toggle', 'label' => 'Sticky Header'],
            ],
        ];
    }

    private static function footer(): array {
        return [
            'label' => 'Footer',
            'icon' => 'minus-square',
            'defaults' => [
                'text' => '© 2026 My Site. All rights reserved.',
                'backgroundColor' => '#111827',
                'textColor' => '#9ca3af',
                'links' => [
                    ['label' => 'Privacy Policy', 'url' => '#'],
                    ['label' => 'Terms of Service', 'url' => '#'],
                ],
                'socialLinks' => [
                    ['platform' => 'facebook', 'url' => '#'],
                    ['platform' => 'twitter', 'url' => '#'],
                    ['platform' => 'instagram', 'url' => '#'],
                ],
            ],
            'schema' => [
                ['key' => 'text', 'type' => 'text', 'label' => 'Copyright Text'],
                ['key' => 'backgroundColor', 'type' => 'color', 'label' => 'Background Color'],
                ['key' => 'textColor', 'type' => 'color', 'label' => 'Text Color'],
                ['key' => 'links', 'type' => 'repeater', 'label' => 'Links', 'fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'url', 'type' => 'text', 'label' => 'URL'],
                ]],
                ['key' => 'socialLinks', 'type' => 'repeater', 'label' => 'Social Links', 'fields' => [
                    ['key' => 'platform', 'type' => 'select', 'label' => 'Platform', 'options' => ['facebook','twitter','instagram','linkedin','youtube']],
                    ['key' => 'url', 'type' => 'text', 'label' => 'URL'],
                ]],
            ],
        ];
    }

    private static function themeSection(): array {
        return [
            'label' => 'Theme Section',
            'icon' => 'layout',
            'defaults' => [
                '_tpl_id' => '',
                '_html' => '',
                '_schema' => '{}',
            ],
            'schema' => [],
        ];
    }
}

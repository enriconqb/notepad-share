<?php

declare(strict_types=1);

namespace App;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

final class MermaidFenceRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (!$node instanceof FencedCode) {
            throw new \InvalidArgumentException('FencedCode expected');
        }
        $info = strtolower(trim(explode(' ', $node->getInfo() ?? '')[0] ?? ''));
        $literal = $node->getLiteral();
        if ($info === 'mermaid') {
            return new HtmlElement('pre', ['class' => 'mermaid'], Xml::escape($literal));
        }
        $attrs = ['class' => $info !== '' ? 'language-' . $info : null];
        $attrs = array_filter($attrs, static fn ($v) => $v !== null);
        $code = new HtmlElement('code', $attrs, Xml::escape($literal));
        return new HtmlElement('pre', [], $code);
    }
}

final class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addRenderer(FencedCode::class, new MermaidFenceRenderer(), 100);
        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $format, string $content): string
    {
        if ($format === 'txt') {
            return '<div class="plain">' . nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</div>';
        }
        $html = $this->converter->convert($content)->getContent();
        return $this->sanitize($html);
    }

    private function sanitize(string $html): string
    {
        $allowed = 'h1,h2,h3,h4,h5,h6,p,br,hr,ul,ol,li,blockquote,pre,code,em,strong,del,a,img,table,thead,tbody,tr,th,td,input,div';
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8"><div id="ns-root">' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('ns-root');
        if (!$root) {
            return '';
        }
        $this->walk($root, array_fill_keys(explode(',', $allowed), true));
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private function walk(\DOMNode $node, array $allowed): void
    {
        if ($node instanceof \DOMElement) {
            $remove = [];
            foreach ([...$node->childNodes] as $child) {
                if ($child instanceof \DOMElement && !isset($allowed[strtolower($child->tagName)])) {
                    $remove[] = $child;
                    continue;
                }
                $this->walk($child, $allowed);
            }
            foreach ($remove as $el) {
                $el->parentNode?->removeChild($el);
            }
            if ($node->tagName !== 'ns-root' && $node->hasAttributes()) {
                $keep = [];
                foreach ([...$node->attributes] as $attr) {
                    $name = strtolower($attr->name);
                    $val = $attr->value;
                    if (str_starts_with($name, 'on')) {
                        continue;
                    }
                    if ($name === 'href' && preg_match('#^(https?:|mailto:|/n/)#i', $val) && !preg_match('#^\s*javascript:#i', $val)) {
                        $keep[$name] = $val;
                    } elseif ($name === 'src' && !preg_match('#^\s*(javascript|data):#i', $val) && preg_match('#/n/[a-z0-9-]+/img/[a-f0-9]+#i', $val)) {
                        $keep[$name] = $val;
                    } elseif (in_array($name, ['alt', 'class'], true)) {
                        $keep[$name] = $val;
                    } elseif ($name === 'type' && strtolower($val) === 'checkbox') {
                        $keep[$name] = 'checkbox';
                    } elseif (in_array($name, ['disabled', 'checked'], true)) {
                        $keep[$name] = $val;
                    }
                }
                while ($node->hasAttributes()) {
                    $node->removeAttribute($node->attributes->item(0)->name);
                }
                foreach ($keep as $k => $v) {
                    $node->setAttribute($k, $v);
                }
                if (strtolower($node->tagName) === 'input') {
                    $node->setAttribute('disabled', 'disabled');
                }
            }
        }
        foreach ([...$node->childNodes] as $child) {
            if ($child instanceof \DOMElement) {
                // already processed children of elements above
            }
        }
    }
}

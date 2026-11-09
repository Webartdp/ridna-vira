<?php

namespace Tests\Unit;

use App\Support\ArticleContent;
use PHPUnit\Framework\TestCase;

class ArticleContentTest extends TestCase
{
    public function test_generic_site_title_is_replaced_by_the_article_heading(): void
    {
        $content = new ArticleContent;
        $html = '<title>Ridna Vira</title><h1>Ridna Vira</h1><table bgcolor="yellow"><tr><td><h2>Пам’ять українського Роду</h2><p>Перший абзац.</p></td></tr></table>';
        $title = $content->title($html, ['title' => 'Ridna Vira']);
        $this->assertSame('Пам’ять українського Роду', $title);
        $body = $content->body($html, $title);
        $this->assertSame('<p>Перший абзац.</p>', $body);
    }

    public function test_layout_is_removed_but_paragraphs_emphasis_and_data_tables_survive(): void
    {
        $content = new ArticleContent;
        $html = '<table width="750" bgcolor="#ffffcc"><tr><td><h2>Назва</h2></td></tr><tr><td background="old.jpg"><blockquote><p><font size="2">Автор <b>дослідження</b></font></p><p>Другий абзац.</p><table><tr><th>Рік</th><th>Подія</th></tr><tr><td>2026</td><td>Зустріч</td></tr></table></blockquote></td></tr></table>';
        $body = $content->body($html, 'Назва');
        $this->assertStringContainsString('<strong>дослідження</strong>', $body);
        $this->assertStringContainsString('<p>Другий абзац.</p>', $body);
        $this->assertStringContainsString('<div class="article-table"><table>', $body);
        $this->assertStringContainsString('<th>Рік</th>', $body);
        $this->assertSame(1, substr_count($body, '<table>'));
        $this->assertStringNotContainsString('bgcolor', $body);
        $this->assertStringNotContainsString('width=', $body);
        $this->assertStringNotContainsString('<font', $body);
    }

    public function test_active_content_and_unsafe_links_are_removed(): void
    {
        $body = (new ArticleContent)->body('<style>body{display:none}</style><script>alert(1)</script><p onclick="evil()">Текст <a href="javascript:alert(1)">посилання</a> <a href="https://example.org/source">джерело</a></p><img src="x" onerror="evil()"><iframe src="https://example.org"></iframe>', 'Стаття');
        $this->assertStringContainsString('Текст посилання', $body);
        $this->assertStringContainsString('href="https://example.org/source"', $body);
        foreach (['<script', '<style', '<iframe', 'onclick', 'onerror', 'javascript:'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $body);
        }
    }

    public function test_prose_breaks_become_paragraphs_and_verse_keeps_its_lines(): void
    {
        $content = new ArticleContent;
        $one = str_repeat('Дослідження традиції. ', 12);
        $two = str_repeat('Пам’ять поколінь. ', 12);
        $body = $content->body('<p>'.$one.'<br>'.$two.'</p><p>Перший рядок<br>Другий рядок</p>', 'Назва');
        $this->assertSame(3, substr_count($body, '<p>'));
        $this->assertStringContainsString('Перший рядок<br>Другий рядок', $body);
    }

    public function test_original_link_label_is_used_if_no_article_heading_exists(): void
    {
        $content = new ArticleContent;
        $this->assertSame('Духовна спадщина', $content->title('<p>Текст статті.</p>', ['title' => 'Ridna Vira', 'source_label' => 'Духовна спадщина (html)']));
        $this->assertSame('Наукове дослідження', $content->cleanTitle('Наукове дослідження (pdf )'));
    }

    public function test_printed_page_numbers_are_removed_and_interrupted_prose_is_joined(): void
    {
        $content = new ArticleContent;
        $body = $content->body('<p>8</p><p>Дослідники вивчають</p><p>9</p><p>традицію українців.</p><p>10</p><p>Наступний абзац.</p>', 'Назва');
        $this->assertStringContainsString('<p>Дослідники вивчають традицію українців.</p>', $body);
        $this->assertStringNotContainsString('<p>8</p>', $body);
        $this->assertStringContainsString('<p>42</p>', $content->body('<p>Результат</p><p>42</p>', 'Назва'));
    }
}

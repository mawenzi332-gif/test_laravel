<?php

namespace Tests\Unit;

use App\Comments\CommentSanitizer;
use Tests\TestCase;

class CommentSanitizerTest extends TestCase
{
    public function test_it_preserves_the_approved_markup_and_safe_links(): void
    {
        $html = app(CommentSanitizer::class)->sanitize('<strong>bold</strong><i>italic</i><code>x</code><a href="https://example.test" title="safe">link</a>');

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<i>italic</i>', $html);
        $this->assertStringContainsString('<code>x</code>', $html);
        $this->assertStringContainsString('href="https://example.test"', $html);
        $this->assertStringContainsString('title="safe"', $html);
    }

    public function test_it_removes_unsupported_markup_handlers_and_dangerous_urls(): void
    {
        $html = app(CommentSanitizer::class)->sanitize('<script>alert(1)</script><strong onclick="alert(1)">safe</strong><a href="javascript:alert(1)">bad</a><img src="data:text/html,x">');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('<strong>safe</strong>', $html);
    }
}

<?php

namespace Zablose\Captcha;

use Illuminate\Hashing\BcryptHasher;
use Intervention\Image\AbstractFont;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Illuminate\Session\Store;

class Captcha
{

    const CHARACTERS = '0123456789abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ImageManager
     */
    protected $image_manager;

    /**
     * @var Store
     */
    protected $session;

    /**
     * @var BcryptHasher
     */
    protected $hasher;

    /**
     * @var Image
     */
    protected $image;

    /**
     * @var array
     */
    protected $backgrounds = [];

    /**
     * @var array
     */
    protected $fonts = [];

    /**
     * Captcha constructor.
     *
     * @param ImageManager $imageManager
     * @param Store        $session
     * @param BcryptHasher $hasher
     */
    public function __construct(
        ImageManager $imageManager,
        Store $session,
        BcryptHasher $hasher
    )
    {
        $this->image_manager = $imageManager;
        $this->session       = $session;
        $this->hasher        = $hasher;

        $this->config      = new Config();
        $this->backgrounds = $this->getBackgrounds();
        $this->fonts       = $this->getFonts();
    }

    /**
     * Create captcha image
     *
     * @param string $config_name
     *
     * @return mixed
     */
    public function create($config_name = 'default')
    {
        return $this
            ->configure($config_name)
            ->image()->contrast()->text()->lines()->sharpen()->invert()->blur()
            ->response();
    }

    /**
     * Captcha check
     *
     * @param string $value
     *
     * @return bool
     */
    public function check($value)
    {
        if (! $this->session->has('captcha'))
        {
            return false;
        }

        $sensitive = $this->session->get('captcha.sensitive');
        $key       = $this->session->get('captcha.key');

        $this->session->remove('captcha');

        return $this->hasher->check(
            $sensitive ? $value : strtolower($value),
            $key
        );
    }

    /**
     * Generate url of the captcha image source.
     *
     * @param string $config_name
     *
     * @return string
     */
    public function url($config_name = 'default')
    {
        return url('captcha/' . $config_name) . '?' . $this->getRandomString(12);
    }

    /**
     * Apply configuration from the file, if present.
     *
     * @param string $config_name
     *
     * @return $this
     */
    private function configure($config_name)
    {
        $this->config->apply($config_name);

        return $this;
    }

    /**
     * Create base image for captcha.
     *
     * @return $this
     */
    private function image()
    {
        $this->image = $this->config->use_background_image
            ? $this->image_manager->make($this->background())->resize(
                $this->config->width,
                $this->config->height
            )
            : $this->image_manager->canvas(
                $this->config->width,
                $this->config->height,
                $this->config->background_color
            );

        return $this;
    }

    /**
     * @return $this
     */
    private function contrast()
    {
        if ($this->config->contrast <> 0)
        {
            $this->image->contrast($this->config->contrast);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function sharpen()
    {
        if ($this->config->sharpen)
        {
            $this->image->sharpen($this->config->sharpen);
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function invert()
    {
        if ($this->config->invert)
        {
            $this->image->invert();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function blur()
    {
        if ($this->config->blur)
        {
            $this->image->blur($this->config->blur);
        }

        return $this;
    }

    /**
     * @return mixed
     */
    private function response()
    {
        return $this->image->response('png', $this->config->quality);
    }

    /**
     * Generate random text, than add it to the image and session.
     *
     * @return $this
     */
    private function text()
    {
        $margin_left = (int) $this->config->width / $this->config->length * 0.02;
        $width       = $this->config->width / $this->config->length;

        $captcha = '';
        for ($i = 0; $i < $this->config->length; $i++)
        {
            $char = $this->getRandomChar($this->config->characters);
            $this->char($char, $margin_left);
            $margin_left += (int) $width * mt_rand(90, 100) / 100;
            $captcha     .= $char;
        }

        $this->session->put('captcha', [
            'sensitive' => $this->config->sensitive,
            'key'       => $this->hasher->make($this->config->sensitive ? $captcha : strtolower($captcha)),
        ]);

        return $this;
    }

    /**
     * Add char to the image.
     *
     * @param string $char
     * @param int    $margin_left
     *
     * @return $this
     */
    private function char($char, $margin_left)
    {
        $size       = $this->config->size();
        $margin_top = mt_rand(1, (int) ($this->config->height - $size) * 1.25);

        $this->image->text($char, $margin_left, $margin_top, function (AbstractFont $font) use ($size)
        {
            $font->file($this->font());
            $font->size($size);
            $font->color($this->config->color());
            $font->valign('top');
            $font->angle($this->config->angle());
        });

        return $this;
    }

    /**
     * Get random image background path from the list.
     *
     * @return string
     */
    private function background()
    {
        return $this->backgrounds[array_rand($this->backgrounds)];
    }

    /**
     * Get random image font path from the list.
     *
     * @return string
     */
    private function font()
    {
        return $this->fonts[array_rand($this->fonts)];
    }

    /**
     * Add random lines to the image.
     *
     * @return $this
     */
    private function lines()
    {
        for ($i = 0; $i < $this->config->lines; $i++)
        {
            $this->line();
        }

        return $this;
    }

    /**
     * Add random line to the image.
     *
     * @return $this
     */
    private function line()
    {
        $half_width  = (int) $this->config->width / 2;
        $half_height = (int) $this->config->height / 2;

        $this->image->line(
            mt_rand(0, $half_width),
            mt_rand(0, $half_height),
            mt_rand($half_width, $this->config->width),
            mt_rand($half_height, $this->config->height),
            function ($draw)
            {
                $draw->color($this->config->color());
            }
        );

        return $this;
    }

    /**
     * @return string[]
     */
    private function getBackgrounds()
    {
        return $this->getFiles($this->config->assets_dir . 'backgrounds', '.png');
    }

    /**
     * @return string[]
     */
    private function getFonts()
    {
        return $this->getFiles($this->config->assets_dir . 'fonts', '.ttf');
    }

    /**
     * Get files from the directory by extension.
     *
     * @param string $path
     * @param string $extension
     *
     * @return string[]
     */
    private function getFiles($path, $extension)
    {
        $files = [];

        foreach (scandir($path) as $key => $item)
        {
            if (stripos($item, $extension) > 1)
            {
                $files[] = $path . '/' . $item;
            }
        }

        return $files;
    }

    /**
     * @param int    $length
     * @param string $characters
     *
     * @return string
     */
    private function getRandomString($length, $characters = Captcha::CHARACTERS)
    {
        $string = '';

        for ($i = 0; $i < $length; $i++)
        {
            $string .= $this->getRandomChar($characters);
        }

        return $string;
    }

    /**
     * @param string $characters
     *
     * @return string
     */
    private function getRandomChar($characters = Captcha::CHARACTERS)
    {
        return $characters[array_rand(str_split($characters))];
    }

}
<?php

namespace Vgrcic\ThumbFactory;

use Illuminate\Http\UploadedFile;
use Exception;
use Closure;

class Thumb {

	/**
	 * Original image resource, unmodified.
	 * @var resource
	 */
	protected $original_resource;

	/**
	 * Ratio of the original image resource.
	 * @var float
	 */
	protected $original_ratio;

	/**
	 * Processed resource.
	 * @var resource
	 */
	protected $resource;

	/**
	 * Desired width in px of the rendered image.
	 * @var int|null
	 */
	protected $width = null;

	/**
	 * Desired height in px of the rendered image.
	 * @var int|null
	 */
	protected $height = null;

	/**
	 * Calculated ratio of desired width and height.
	 * @var int|null
	 */
	protected $ratio = null;

	/**
	 * Name that will be used when persisting the file.
	 * @var string|null
	 */
	protected $name = null;

	/**
	 * Image name processor.
	 * @var ProcessesFilename|Closure|string
	 */
	protected $name_processor = null;

	/**
	 * Mime type of the processed file.
	 * @var string|null
	 */
	protected $mime = null;

	/**
	 * Name of the function for extracting image resource.
	 * @var string|null
	 */
	protected $creator = null;

	/**
	 * Name of the function for rendering image resource.
	 * @var string|null
	 */
	protected $renderer = null;

	/**
	 * Mime type => function pairs for getting resource from path.
	 * @var array
	 */
	protected $creators = [
		'image/jpeg' => 'imagecreatefromjpeg',
		'image/png' => 'imagecreatefrompng',
		'image/bmp' => 'imagecreatefromwbmp',
		'image/gif' => 'imagecreatefromgif',
	];

	/**
	 * Mime type => function pairs for rendering image from resource.
	 * @var array
	 */
	protected $renderers = [
		'image/jpeg' => 'imagejpeg',
		'image/png' => 'imagepng',
		'image/bmp' => 'imagebmp',
		'image/gif' => 'imagegif',
	];

	/**
	 * Sets the width of the image.
	 * 
	 * @param int $width
	 */
	public function setWidth(?int $width) {
		$this->width = $width;
		$this->calculateRatio();
		return $this;
	}

	/**
	 * Sets the height of the image.
	 * 
	 * @param int $height
	 */
	public function setHeight(?int $height) {
		$this->height = $height;
		$this->calculateRatio();
		return $this;
	}

	/**
	 * Sets the width and height of the image.
	 * 
	 * @param int $width
	 * @param int $height
	 */
	public function setSize(?int $width, ?int $height) {
		$this->width = $width;
		$this->height = $height;
		$this->calculateRatio();
		return $this;
	}

	/**
	 * 
	 * 
	 * @return void
	 */
	protected function calculateRatio() {
		if (isset($this->width, $this->height)) {
			$this->ratio = $this->width / $this->height;
		} else $this->ratio = null;
	}

	/**
	 * Returns rendered image resource.
	 * 
	 * @return resource
	 */
	public function render() {
		$this->processResource();
		return call_user_func($this->renderer, $this->resource);
	}

	/**
	 * Persists the image to a specified path, with a specified name.
	 * 
	 * @param  string $path
	 * @param  string $name
	 * @return void
	 */
	public function save($path = '', $name = null) {
		$this->processResource();
		
		if (!empty($path) && !file_exists($path)) {
			mkdir($path, 0777, true);
		}

		$name = $name ?? $this->name;
		$full_path = trim($path.'/'.$name, '/');
		call_user_func($this->renderer, $this->resource, $full_path);
		return $this;
	}

	/**
	 * Applies neccessary scale/crop actions on the resource.
	 * 
	 * @return void
	 */
	protected function processResource() {
		$this->resource = $this->original_resource;

		if ($this->isExactSize()) return;
		$this->scaleResource();

		if ($this->isExactRatio()) return;
		$this->cropResource();
	}

	/**
	 * Scales the resource.
	 * 
	 * @return void
	 */
	protected function scaleResource() {
		if ($this->width !== null && ($this->height === null || $this->ratio > $this->original_ratio)) {
			// scale by width
			$this->resource = imagescale($this->resource, $this->width);
		} else {
			// scale by height and original ratio
			$this->resource = imagescale($this->resource, ceil($this->height * $this->original_ratio));
		}
	}

	/**
	 * 
	 * 
	 * @return void
	 */
	protected function cropResource() {
		if ($this->ratio < $this->original_ratio) {
			// crop width
			$offset = floor((imagesx($this->resource) - $this->width) / 2);
			$this->resource = imagecrop($this->resource, [
				'x' => $offset,
				'y' => 0,
				'width' => $this->width,
				'height' => $this->height,
			]);
		} else {
			// crop height
			$offset = floor((imagesy($this->resource) - $this->height) / 2);
			$this->resource = imagecrop($this->resource, [
				'x' => 0,
				'y' => $offset,
				'width' => $this->width,
				'height' => $this->height,
			]);
		}
	}

	/**
	 * Indicates if the image already has the correct size.
	 * 
	 * @return boolean
	 */
	protected function isExactSize() {
		return (imagesx($this->resource) === $this->width || $this->width === null) &&
				(imagesy($this->resource) === $this->height || $this->height === null);
	}

	/**
	 * Indicates if the image already has the correct ratio.
	 * 
	 * @return boolean
	 */
	protected function isExactRatio() {
		return $this->ratio === null || $this->ratio === $this->original_ratio;
	}

	/**
	 * Resets settings to initial values.
	 * 
	 * @return void
	 */
	public function reset() {
		$this->resource = $this->original_resource;
		$this->width = $this->height = $this->ratio = null;
		return $this;
	}

	/**
	 * Creates an instance.
	 * 
	 * @param  mixed $file
	 * @return Thumb
	 */
	public static function create($file) {
		return new static($file);
	}

	/**
	 * 
	 * 
	 * @param mixed $file
	 */
	public function __construct($file) {
		$this->setResource($file);
	}

	/**
	 * 
	 * 
	 * @param mixed $file
	 */
	public function setResource($file) {
		if ($file instanceof UploadedFile) {
			$this->setResourceFromSymfonyFile($file);
		} else if (is_array($file)) {
			$this->setResourceFromUpload($file);
		} else {
			$this->setResourceFromPath($file);
		}

		$this->original_ratio = imagesx($this->original_resource) / imagesy($this->original_resource);
		$this->renderer = $this->renderers[$this->mime];
		$this->creator = $this->creators[$this->mime];
		return $this;
	}

	/**
	 * 
	 * 
	 * @param UploadedFile $file
	 * @return Thumb
	 */
	protected function setResourceFromSymfonyFile(UploadedFile $file) {
		$this->mime = $mime = $file->getMimeType();

		if (!$this->isSupportedMime()) {
			throw new Exception('Mime type '.$mime.' is not supported.');
		}

		$this->original_resource = $this->creators[$mime]($file);
		$this->processName($file->getClientOriginalName());
	}

	/**
	 * 
	 * 
	 * @param string $path
	 * @return Thumb
	 */
	protected function setResourceFromUpload($data) {
		if (empty($data['name']) || empty($data['tmp_name'])) {
			throw new Exception('Data does not contain "name" and "tmp_name" attributes.');
		}

		$this->mime = $mime = mime_content_type($data['tmp_name']);

		if (!$this->isSupportedMime()) {
			throw new Exception('Mime type '.$mime.' is not supported.');
		}

		$this->original_resource = $this->creators[$mime]($data['tmp_name']);
		$this->processName($data['name']);
	}

	/**
	 * 
	 * 
	 * @param string $path
	 */
	protected function setResourceFromPath($path) {
		$this->mime = $mime = mime_content_type($path);

		if (!$this->isSupportedMime()) {
			throw new Exception('Mime type '.$mime.' is not supported.');
		}

		$this->original_resource = $this->creators[$mime]($path);

		$pieces = explode('/', str_replace('\\', '/', $path));
		$this->processName(array_pop($pieces));
	}

	/**
	 * Indicates if a mime type is supported.
	 * 
	 * @return boolean
	 */
	protected function isSupportedMime() {
		return array_key_exists($this->mime, $this->creators);
	}

	/**
	 * Sets the name processor.
	 * 
	 * @param Nameprocessor|Closure|string|null  $processor
	 * @return Thumb
	 */
	public function setNameProcessor($processor) {
		if ($processor instanceof Closure || $processor instanceof ProcessesFilename || is_null($processor) || function_exists($processor)) {
			$this->name_processor = $processor;

			if (!is_null($this->name)) {
				$this->processName($this->name);
			}

			return $this;
		}

		throw new Exception('Name processor must be a function name, a Closure, null, or an instance
							of a class implementing Vgrcic\ThumbFactory\ProcessesFilename interface');
	}

	/**
	 * Processes the original image name using the name processor.
	 * 
	 * @return void
	 */
	protected function processName($name) {
		if ($this->name_processor === null) {
			$this->name = $name;
			return;
		}

		if ($this->name_processor instanceof Closure || is_string($this->name_processor)) {
			$this->name = call_user_func($this->name_processor, $name);
		} else {
			$this->name = $this->name_processor->processFilename($name);
		}
	}

	/**
	 * Sets the value that will be used as name when persisting.
	 * 
	 * @param string $name
	 * @return Thumb
	 */
	public function setName($name) {
		$this->name = $name;
		return $this;
	}

	/**
	 * Returns the processed name of the image.
	 * 
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

}

?>

<?php

namespace MediaEmbed\Object;

use MediaEmbed\Object\ObjectInterface;

/**
 * A generic object - for now.
 *
 * TODO: Implement audio, video separatly
 */
class MediaObject implements ObjectInterface {

	protected $_stub;

	protected $_match;

  protected $_objectAttributes = array();

  protected $_objectParams = array();

	public $config = array(
		'prefer' => 'iframe' // Type object or iframe (only available for few, fallback will be object)
	);

	/**
	 * MediaObject::__construct()
	 *
	 * @param array $config
	 */
	public function __construct(array $stub, array $config) {
		$this->config = $config += $this->config;

		$stubDefaults = array(
			'id' => '',
			'name' => '',
			'website' => '',
			'slug' => '',
			'match' => array()
		);
		$this->_stub = $stub + $stubDefaults;
		$this->_match = $this->_stub['match'];
		$this->_stub['id'] = $this->id();

		$this->_setDefaultParams($stub);

		/*
		// iframe or object?
		if (isset($stub['iframe-player'])) {
			if ($config['prefer'] === 'iframe') {
				$src = $this->getObjectSrc($data, 'iframe-player');
				$stub['iframe-player'] = $src;
				return true;
			}
			unset($stub['iframe-player']);
		}
		*/
		$type = 'embed-src';
		if (isset($this->_stub['iframe-player'])) {
			if ($this->config['prefer'] === 'iframe') {
				$type = 'iframe-player';
			}
		}

		if ($type === 'iframe-player') {
			$src = $this->_getObjectSrc($type);
			$this->_stub['iframe-player'] = $src;

			$this->setParam('movie', $src);
			$this->setAttribute('data', $src);
		}
		if (!empty($this->_stub['reverse'])) {
			$flashvars = $this->getParams('flashvars');
			$this->setParam('flashvars', str_replace('$2', $this->_stub['id'], $flashvars));
		}
	}

  /**
   * Getter/setter for stub
   *
   * @param string $property - (optional) the specific
   *   property of the stub to be returned. If
   *   omitted, array of all properties are returned.
   *
   * @return array|string|$this
   */
  public function stub($property = null, $value = null) {
  	if ($property === null) {
			return $this->_stub;
  	}
  	if ($value === null) {
    	return isset($this->_stub[$property]) ? $this->_stub[$property] : null;
  	}
  	return $this;
  }

	/**
	 * {@inheritdoc}
	 */
	public function id() {
		$res = $this->_match;

		if (empty($this->_stub['id'])) {
			if (empty($res[count($res) - 1])) {
				return '';
			}
			$this->_stub['id'] = $res[count($res) - 1];
		}
		$id = $this->_stub['id'];

		for ($i = 1; $i <= count($res); $i++) {
			$id = str_ireplace('$' . $i, $res[$i - 1], $id);
		}
		return $id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function slug() {
		return $this->_stub['slug'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function name() {
		$res = $this->_match;

		if (empty($this->_stub['name'])) {
			return '';
		}
		$name = $this->_stub['name'];

		for ($i = 1; $i <= count($res); $i++) {
			$name = str_ireplace('$' . $i, $res[$i - 1], $name);
		}
		return $name;
	}

	/**
	 * Return the website URL of this type
	 *
	 * @return string
	 */
	public function website() {
		return !empty($this->_stub['website']) ? $this->_stub['website'] : '';
	}

	/**
	 * Returns a png img
	 *
	 * @param array $stub or string $alias
	 * @return Resource or null if not available
	 */
	public function icon() {
		$url = $this->_stub['website'];
		if (!$url) {
			return;
		}

		$pieces = parse_url($url);
		$url = $pieces['host'];

		$icon = 'http://www.google.com/s2/favicons?domain=';
		$icon .= $url;

		$context = stream_context_create(
			array('http' => array('header' => 'Connection: close')));
		// E.g. http://www.google.com/s2/favicons?domain=xyz.com
		$file = file_get_contents($icon, 0, $context);
		if ($file === false) {
			return null;
		}
		// TODO: transform into 16x16 png
		return $file;
	}

	/**
	 * @param string location Absolute path with trailing slash
	 * @param binary Icon Icon data
	 * @return string|null $filename
	 */
	public function saveIcon($location = null, $icon = null) {
		if ($icon === null) {
			$icon = $this->icon();
		}
		if (!$icon) {
			return;
		}
		if (!$location) {
			$location = IMAGES . 'content' . DS . 'video_types';
			if (!is_dir($location)) {
				mkdir($location, 0755, true);
			}
			$location .= DS;
		}
		$filename = $this->slug() . '.png';
		$file = $location . $filename;
		if (!file_put_contents($file, $icon)) {
			return;
		}
		return $filename;
	}

  /**
   * Override a default object param value
   *
   * @param $param mixed - the name of the param to be set
   *                       or an array of multiple params to set
   * @param $value string - (optional) the value to set the param to
   *                        if only one param is being set
   *
   * @return $this
   */
  public function setParam($param, $value = null) {
    if (is_array($param)) {
      foreach ($param as $p => $v) {
        $this->_objectParams[$p] = $v;
      }

    } else {
      $this->_objectParams[$param] = $value;
    }

		return $this;
  }

  /**
   * Override a default object attribute value
   *
   * @param $param mixed - the name of the attribute to be set
   *                       or an array of multiple attribs to be set
   * @param $value string - (optional) the value to set the param to
   *                        if only one param is being set
   *
   * @return $this
   */
  public function setAttribute($param, $value = null) {
    if (is_array($param)) {
      foreach ($param as $p => $v) {
        $this->_objectAttributes[$p] = $v;
      }

    } else {
      $this->_objectAttributes[$param] = $value;
    }

    return $this;
  }

  /**
   * Set the height of the object
   *
   * @param mixed - height to set the object to
   *
   * @return boolean - true if the value was set, false
   *                   if parseURL hasn't been called yet
   */
  public function setHeight($height) {
    return $this->setAttribute('height', $height);
  }

  /**
   * Set the width of the object
   *
   * @param mixed - width to set the object to
   *
   * @return boolean - true if the value was set, false
   *                   if parseURL hasn't been called yet
   */
  public function setWidth($width) {
    return $this->setAttribute('width', $width);
  }

  /**
   * Return object params about the video metadata
   *
   * @return array|string - object params
   */
  public function getParams($key = null) {
  	if ($key === null) {
    	return $this->_objectParams;
    }
    if (!isset($this->_objectParams[$key])) {
    	return null;
    }
    return $this->_objectParams[$key];
  }

  /**
   * Return object attribute
   *
   * @return array - object attribute
   */
  public function getAttributes($key = null) {
  	if ($key === null) {
    	return $this->_objectAttributes;
  	}
		if (!isset($this->_objectAttributes[$key])) {
    	return null;
    }
    return $this->_objectAttributes[$key];
  }

  /**
   * Convert the url to an embedable tag
   *
   * return string - the embed html
   */
  public function getEmbedCode() {
    if (!empty($this->_stub['iframe-player']) && $this->config['prefer'] === 'iframe') {
      return $this->_buildIframe();
    }
    return $this->_buildObject();
  }

  /**
   * Getter/setter of what this Object currently prefers as output type
   *
   * @return $this|string
   */
  protected function prefers($type = null) {
  	if ($type === null) {
  		$prefers = 'objcet';
  		if (!empty($this->_stub['iframe-player']) && $this->config['prefer'] === 'iframe') {
  			$prefers = 'iframe';
  		}
  		return $prefers;
  	}
  	$this->config['prefer'] = $type;
  	return $this;
  }

	/**
	 * Get final src
	 *
	 * @param string $type
	 * @return string|null
	 */
	protected function _getObjectSrc($type = 'embed-src') {
		if (empty($this->_stub['id']) || empty($this->_stub['slug'])) {
			return;
		}

		// src
		$src = str_replace('$2', $this->_stub['id'], $this->_stub[$type]);
		if (!empty($host['replace'])) {
			foreach ($host['replace'] as $placeholder => $replacement) {
				$src = str_replace($placeholder, $replacement, $src);
			}
		}
		return $src;
	}

	/**
	 * VideoLib::getImageSrc()
	 *
	 * @param array $data
	 * @return string|null
	 */
	public function getImageSrc($data) {
		if (empty($this->_stub['id'])) {
			return;
		}
		if (empty($this->_stub['image-src'])) {
			return;
		}
		//$id = isset($this->_stub['id']) ? $this->_stub['id'] : '$2';

		$src = str_replace('$2', $this->_stub['id'], $this->_stub['image-src']);
		return $src;
	}

  /**
   * Return a thumbnail for the embeded video
   *
   * @return string - the thumbnail href
   */
  public function image() {
    if (empty($this->_stub['image-src'])) {
    	return '';
		}
    $thumb = $this->_stub['image-src'];

    for ($i = 1; $i <= count($this->_match); $i++) {
      $thumb = str_ireplace('$'.$i, $this->_match[$i - 1], $thumb);
    }

    return $thumb;
  }

	/**
	 * Convenience wrapper for `echo $MediaObject`
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getEmbedCode();
	}

  /**
   * Build a generic object skeleton
   *
   * @return string
   */
  protected function _buildObject() {
    $objectAttributes = $objectParams = '';

    foreach ($this->_objectAttributes as $param => $value) {
      $objectAttributes .= ' ' . $param . '="' . $value . '"';
    }

    foreach ($this->_objectParams as $param => $value) {
      $objectParams .= '<param name="' . $param . '" value="' . $value . '" />';
    }

    if (!$objectAttributes && !$objectParams) {
    	return '';
    }
    return sprintf("<object %s> %s</object>", $objectAttributes, $objectParams);
  }

  /**
   * Build an iFrame player
   *
   * @return string
   */
  protected function _buildIframe() {
    $source = $this->_stub['iframe-player'];

    for ($i = 1; $i <= count($this->_match); $i++) {
      $source = str_ireplace('$' . $i, $this->_match[$i - 1], $source);
    }

    $width = $this->_objectAttributes['width'];
    $height = $this->_objectAttributes['height'];
		# Transparent hack (http://groups.google.com/group/autoembed/browse_thread/thread/0ecdd9b898e12183)
    return sprintf('<iframe type="text/html" width="%s" height="%s" src="%s?wmode=transparent" frameborder="0"></iframe>', $width, $height, $source);
  }

  /**
   * Set the default params for the type of
   * stub we are working with
   *
   * @return void
   */
  protected function _setDefaultParams($stub) {
    $source = $stub['embed-src'];
    $flashvars = (isset($stub['flashvars']))? $stub['flashvars'] : null;

    for ($i = 1; $i <= count($this->_match); $i++) {
      $source = str_ireplace('$' . $i, $this->_match[$i - 1], $source);
      $flashvars = str_ireplace('$' . $i, $this->_match[$i - 1], $flashvars);
    }

    $source = $this->_esc($source);
    $flashvars = $this->_esc($flashvars);

    $this->_objectParams = array(
			'movie' => $source,
			'quality' => 'high',
			'allowFullScreen' => 'true',
			'allowScriptAccess' => 'always',
			'pluginspage' => 'http://www.macromedia.com/go/getflashplayer',
			'autoplay' => 'false',
			'autostart' => 'false',
			'flashvars' => $flashvars,
		);

		$this->_objectAttributes = array(
			'type' => 'application/x-shockwave-flash',
			'data' => $source,
			'width' => $stub['embed-width'],
			'height' => $stub['embed-height'],
		);
  }

	/**
	 * MediaObject::_esc()
	 *
	 * @param string $text
	 * @return string
	 */
	protected function _esc($text) {
		return htmlspecialchars($text, ENT_QUOTES, null, false);
	}

}
<?php

namespace Vgrcic\ThumbFactory;

interface ProcessesFilename {

	/**
	 * Returns a processed filename.
	 * 
	 * @param  string $name
	 * @return string
	 */
	public function processFilename($filename);

}

?>

<?php

namespace Symfony\Component\HttpFoundation\File;

use Laravoole\UploadedFile;



function move_uploaded_file($src, $dest) {
	if(!is_uploaded_file($src))  {
		trigger_error('not an uploaded file');
		return false;
	}
	if(rename($src, $dest)) {
		unset(UploadedFile::$files[$src]);
		return true;
	} else {
		return false;
	}

}

function is_uploaded_file($name) {
	return isset(UploadedFile::$files[$name]);
}

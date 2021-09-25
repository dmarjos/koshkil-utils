<?php
namespace Koshkil\Utilities;

use Koshkil\Core\Application;
use Koshkil\Core\Models\Gallery;

class FileUtilities {

    public static function getFileIcon($fileName,$fontawesome=false) {
        $parts=explode(".",basename($fileName));
        $extension=array_pop($parts);
        switch ($extension) {
            case "ppt":
            case "pptx":
                $retVal=$fontawesome?'fa-file-powerpoint-o':'powerpoint.png';
                break;

            case "pdf":
                $retVal=$fontawesome?'fa-file-pdf-o':'pdf.png';
                break;

            case "xls":
            case "xlsx":
                $retVal=$fontawesome?'fa-file-excel-o':'excel.png';
                break;

            case "doc":
            case "docx":
                $retVal=$fontawesome?'fa-file-word-o':'word.png';
                break;
            default:
                $retVal=$fontawesome?'fa-file':'desconocido.png';
                break;
        }
        return $retVal;
    }
    public static function getFilePath($fileType,$fileId,$mkdir=false) {
        $fileName=substr(md5($fileId),-8);
        $path=array("uploads");
        foreach(explode("/",$fileType) as $folder) $path[]=$folder;
        $path[]=substr($fileName,0,2);
        $path[]=substr($fileName,2,2);
        $fullPath=Application::get('DOCUMENT_ROOT');
        if ($mkdir) {
            foreach($path as $folder) {
                $fullPath.="/{$folder}";
                if (!file_exists($fullPath)) mkdir($fullPath,0777);
            }
        }
        return Application::getPath(implode("/",$path));
    }

    public static function mkdir($folder) {
        $path=array();
        foreach(explode("/",$folder) as $folder) $path[]=$folder;
        $fullPath=Application::get("DOCUMENT_ROOT").Application::get('BASE_DIR');
        foreach($path as $folder) {
            if (!is_writable($fullPath)) {
                echo "{$fullPath} not writable\n";
                return false;
            };
            $fullPath.="/{$folder}";
            if (!file_exists($fullPath)) mkdir($fullPath,0777);
        }
        return true;
    }

    public static function processSingleUpload($name,$record,$group,$type,$usage,$deleteCurrent=false,$useFTP=true) {
        if ($deleteCurrent && is_array($deleteCurrent)) {
            $_options=$deleteCurrent;
            $deleteCurrent=$_options["deleteCurrent"];
            $uploadDirectory=Application::getLink($_options["uploadDirectory"]);
        }
        if (!$uploadDirectory)
            $uploadDirectory=Application::getLink('/resources/uploads/'.$group);

        $physUploadDirectory=Application::get('DOCUMENT_ROOT').$uploadDirectory;
        if (!file_exists($physUploadDirectory))
            mkdir($physUploadDirectory,0777,true);

        $theUpload=$_FILES[$name];
        if (isset($theUpload) && $theUpload["error"]==0) {

    		$splat=explode(".",$theUpload["name"]);
    		$extension=strtolower(array_pop($splat));
    		$fname=stringUtils::makeUrl(implode("-",$splat));
    		$idx=1;
    		while(file_exists($physUploadDirectory."/".$fname.".".$extension))
    			$fname=stringUtils::makeUrl(strtolower(implode("-",$splat))).($idx++);

    		$newResource=array(
    			"gal_tipo"=>$type,
    			"gal_mime"=>$theUpload["type"],
                "gal_archivo"=>$fname.".".$extension,
                "gal_fecha"=>date("d/m/Y",time()),
                "gal_titulo"=>"",
                "gal_descripcion"=>"",
    			'gal_indice'=>'1',
                'gal_uso'=>$usage,
    			"gal_grupo"=>$group,
                "gal_temp_id"=>"",
                "gal_relacionado"=>$record->recordId()
    		);

            if ($deleteCurrent) {
                $resource=TMGallery::where('gal_grupo',$group)
                    ->where('gal_relacionado',$record->recordId)
                    ->where('gal_uso',$usage)
                    ->first();
                if ($resource) {
                    if (file_exists($physUploadDirectory."/".$resource->gal_archivo)) {
                        @unlink($physUploadDirectory."/".$resource->gal_archivo);
                    }
                    $resource->fill($newResource)->update();
                } else {
                    $resource=TMGallery::create($newResource);
                }
            } else {
                $resource=TMGallery::create($newResource);
            }
    		move_uploaded_file($theUpload["tmp_name"],$physUploadDirectory."/".$fname.".".$extension);
            if ($useFTP && $record->owner && $record->owner->hasFtp) {
                $record->owner->uploadFtp($physUploadDirectory."/".$fname.".".$extension,$uploadDirectory."/".$fname.".".$extension);
            }
        }
    }

    public static  function processMultipleUpload($name,$record,$group,$options=false) {
        if ($options && is_array($options)) {
            $uploadDirectory=Application::getLink($options["uploadDirectory"]);
        }
        if (!$uploadDirectory)
            $uploadDirectory=Application::getLink('/resources/uploads/'.$group);

        $physUploadDirectory=Application::get('DOCUMENT_ROOT').$uploadDirectory;

        if (!file_exists($physUploadDirectory))
            @mkdir($physUploadDirectory,0777,true);

        $lastIndex=TMGallery::lastIndex($group,$record->recordId());
        if (is_array($_FILES[$name]["name"])) {
            $_tu=array();
            foreach(array_keys($_FILES[$name]) as $key) {
                foreach($_FILES[$name][$key] as $index=>$value) {
                    $_tu[$index][$key]=$value;
                }
            }
            $theUploads=$_tu;
        }

        foreach($theUploads as $theUpload) {
            if ($theUpload["error"]==0) {
                $lastIndex++;

                $splat=explode(".",$theUpload["name"]);
                $extension=strtolower(array_pop($splat));
                $fname=stringUtils::makeUrl(implode("-",$splat));
                $idx=1;
                while(file_exists($physUploadDirectory."/".$fname.".".$extension))
                    $fname=stringUtils::makeUrl(strtolower(implode("-",$splat))).($idx++);

                $newResource=array(
                    "gal_tipo"=>"image",
                    "gal_mime"=>$theUpload["type"],
                    "gal_archivo"=>$fname.".".$extension,
                    "gal_stillframe"=>"",
                    "gal_titulo"=>"",
                    "gal_descripcion"=>"",
                    'gal_indice'=>$lastIndex,
                    'gal_uso'=>'general',
                    "gal_grupo"=>$group,
                    "gal_temp_id"=>"",
                    "gal_relacionado"=>$record->recordId()
                );

                $resource=TMGallery::create($newResource);
                move_uploaded_file($theUpload["tmp_name"],$physUploadDirectory."/".$fname.".".$extension);
                if ($record->owner && $record->owner->hasFtp) {
                    $record->owner->uploadFtp($physUploadDirectory."/".$fname.".".$extension,$uploadDirectory."/".$fname.".".$extension);
                }
            }

        }
    }

    public static function uploadedFileIsValid($name,$requirements=false) {
        $retVal=array(
            "status"=>"ok",
            "reason"=>array()
        );
        list($name,$index)=explode("|",$name);
        if (!is_null($index)) $index=intval($index);
        if (!$requirements) {
            $requirements=array(
                "max_size"=>ini_get("upload_max_filesize")
            );
        }
        $theUpload=$_FILES[$name];
        if (!$theUploads) return $retVal;
        if (is_array($theUpload["name"]) && !is_null($index) && isset($theUpload["name"][$index])) {
            $_tu=array();
            foreach(array_keys($theUpload) as $key) {
                $_tu[$key]=$theUpload[$key][$index];
            }
            $theUpload=$_tu;
        }
        $maxSize=numberUtils::stringToNumber($requirements["max_size"]);
        if ($theUpload["size"]>$maxSize) {
            $retVal["status"]="fail";
            $retVal["reason"][]="El tama&ntilde;o no debe ser mayor a ".$requirements["max_size"];
        }
        if($requirements["type"]) {
            if (!preg_match_all("#{$requirements["type"]}#si",$theUpload["type"],$matches)) {
                $retVal["status"]="fail";
                $retVal["reason"][]="Tipo de archivo incorrecto";
                KoshkilLog::error([$theUpload["type"],$requirements["type"]]);
            }
        }
        if ($requirements["min_width"] || $requirements["min_height"]) {
            if (!imagesUtils::loadImage($theUpload["tmp_name"])) {
                $retVal["status"]="fail";
                $retVal["reason"][]="Tipo de archivo incorrecto";
            } else {
                if ($requirements["min_width"] && imagesUtils::getWidth()<intval($requirements["min_width"])) {
                    $retVal["status"]="fail";
                    $retVal["reason"][]="La imagen debe tener como m&iacute;nimo {$requirements["min_width"]}px de ancho";
                    KoshkilLog::error([imagesUtils::getWidth(),$requirements["min_width"]]);
                }
                if ($requirements["min_height"] && imagesUtils::getHeight()<intval($requirements["min_height"])) {
                    $retVal["status"]="fail";
                    $retVal["reason"][]="La imagen debe tener como m&iacute;nimo {$requirements["min_height"]}px de alto";
                    KoshkilLog::error([imagesUtils::getHeight(),$requirements["min_height"]]);
                }
            }
        }
        if ($requirements["max_width"] || $requirements["max_height"]) {

            if (!imagesUtils::isLoaded())
                imagesUtils::loadImage($theUpload["tmp_name"]);

            if (!imagesUtils::isLoaded()) {
                $retVal["status"]="fail";
                $retVal["reason"][]="Tipo de archivo incorrecto";
            } else {
                if ($requirements["max_width"] && imagesUtils::getWidth()>intval($requirements["max_width"])) {
                    $retVal["status"]="fail";
                    $retVal["reason"][]="La imagen debe tener como m&aacute;ximo {$requirements["max_width"]}px de ancho";
                    KoshkilLog::error([imagesUtils::getWidth(),$requirements["max_width"]]);
                }
                if ($requirements["max_height"] && imagesUtils::getHeight()>intval($requirements["max_height"])) {
                    $retVal["status"]="fail";
                    $retVal["reason"][]="La imagen debe tener como m&aacute;ximo {$requirements["max_height"]}px de alto";
                    KoshkilLog::error([imagesUtils::getWidth(),$requirements["max_height"]]);
                }
            }
        }
        $retVal["reason"]=implode("<br/>",$retVal["reason"]);
        return $retVal;
    }


    public static function createDirectory($path) { self::mkdir($path); }

    public static function removeFolder($rootDir) {
		if (!$rootDir) {
			throw new Exception('No se ha indicado la carpeta a borrar');
		}
		if (!file_exists($rootDir) || !is_dir($rootDir)) return;

		$dir=dir($rootDir);
		while($entry=$dir->read()) {
			if (in_array($entry,['.','..'])) continue;
			if (is_dir($rootDir."/".$entry)) {
				self::removeFolder($rootDir."/".$entry);
			} else {
				@unlink($rootDir."/".$entry);
			}
		}
		@rmdir($rootDir);
		$dir->close();
	}


}

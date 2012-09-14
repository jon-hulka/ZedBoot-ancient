<?php
/*
 * Copyright 2012 Jonathan Hulka (jon.hulka@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 */
namespace cms\utilities;
class Imaging
{
	/**
	 * Creates an image and thumbnail.
	 * @param $replaceName if specified, the image with this name will be deleted first.
	 * @return String image name.
	 */
	public static function uploadImage($inputName,$width,$height,$thumbWidth,$thumbHeight,$savePath,$replaceName='')
	{
		$result=false;
		$savePath=rtrim($savePath,'/').'/';
		if(isset($_FILES[$inputName]['name'])&&$_FILES[$inputName]['name']!='')
		{
			$fileName=$_FILES[$inputName]['name'];
			$fileType=$_FILES[$inputName]['type'];
			$fileSize=$_FILES[$inputName]['size'];
			$tempName=$_FILES[$inputName]['tmp_name'];
			if(is_uploaded_file($tempName))
			{
				$imageAttributes=getimagesize($tempName);
				if($imageAttributes!==false)
				{
					$info = pathinfo($fileName);
					$fileName =  basename($fileName,'.'.$info['extension']);
					$toName=$fileName.'.jpg';
					$i=2;
					//Get a unique save path
					//Hopefully nobody else jumps in and grabs it in the meantime...
					//Could implement a mutex, but that seems like overkill
					while(is_file($savePath.$toName))
					{
						$toName=$fileName.'('.$i++.').jpg';
					}
					list($oWidth,$oHeight,$oType,$oAttr)=$imageAttributes;
					$sourceImage=false;
					switch ($oType)
					{
						case IMAGETYPE_GIF: $sourceImage=ImageCreateFromGIF($tempName); break;
						case IMAGETYPE_JPEG: $sourceImage=ImageCreateFromJPEG($tempName); break;
						case IMAGETYPE_PNG: $sourceImage=imagecreatefromPNG($tempName); break;
					}
					if($sourceImage!==false)
					{
						if(!empty($replaceName))
						{
							$ri=$savePath.$replaceName;
							$rt=$savePath.'thumbs/'.$replaceName;
							@unlink($ri);
							@unlink($rt);
						}
						$toPath=$savePath.$toName;
						$thumbPath=$savePath.'thumbs/'.$toName;
						@unlink($toPath);
						@unlink($thumbPath);
						self::scaleImage($sourceImage,$imageAttributes,$toPath,$width,$height,IMAGETYPE_JPG);
						self::scaleImage($sourceImage,$imageAttributes,$thumbPath,$thumbWidth,$thumbHeight,IMAGETYPE_JPG,true);
						imagedestroy($sourceImage);
						$result=$toName;
					}
				}
			}
		}
		return $result;
	}
	/**
	 * @param $sourceImage resource image to scale - as returned by ImageCreateFromXXX($path)
	 * @param $attributes array image attributes (w,h,type,width/height tag) - as per php's getImageSize($path) - this is here to avoid multiple calls to getImageSize()
	 * @param $destPath string
	 * @param $destWidth number
	 * @param $destHeight number
	 * @param $destType int IMAGETYPE_PNG or IMAGETYPE_JPG (at the moment - more can be added)
	 * @param $clip boolean should the image be clipped to fit? If false, the image will be scaled to fit within the destination dimensions.
	 * @param $pad boolean if not clipped, should the image be padded to aspect ratio?
	 * @param $grow boolean should the image be scaled up if applicable?
	 * @param $colorHandle int|null fill color as a 24-bit rgb value (ie 0x00808080)- I'm not sure if the alpha bits work for png images
	 */
	public static function scaleImage($sourceImage,$attributes,$destPath,$destWidth,$destHeight,$destType,$clip=false,$pad=false,$grow=false,$colorHandle=null)
	{
		$ok=false;
		
		//First the complicated bit:
		//determine the relative aspect ratio
		$aspect=$destWidth*$attributes[1]/$attributes[0]/$destHeight;
		$scaleWidth=$destWidth;
		$scaleHeight=$destHeight;
		//Indicates whether $grow=false actually prevented image scaling
		$throttled=false;
		if(($aspect>1&&$clip)||($aspect<=1&&!$clip))
		{
			//scale to width (apply scaleWidth:sourceWidth ratio to height)
			//If image is not to be scaled up - check and adjust the final width
			if(!$grow&&$scaleWidth>$attributes[0])
			{
				$throttled=true;
				$scaleWidth=$attributes[0];
			}
			$scaleHeight=$scaleWidth*$attributes[1]/$attributes[0];
		}
		else
		{
			//scale to height (apply scaleHeight:sourceHeight ratio to width)
			//If image is not to be scaled up - check and adjust the final height
			if(!$grow&&$scaleHeight>$attributes[1])
			{
				$throttled=true;
				$scaleHeight=$attributes[1];
			}
			$scaleWidth=$scaleHeight*$attributes[0]/$attributes[1];
		}
		
		//Adjust image dimensions if not padding
		if(!$clip&&!$pad)
		{
			$destHeight=$scaleHeight;
			$destWidth=$scaleWidth;
		}
		
		$destImage=imagecreatetruecolor($destWidth,$destHeight);
		//enable transparency for png images
		if($destType==IMAGETYPE_PNG)
		{
			imagealphablending($destImage, false);
			imagesavealpha($destImage, true);
		}
		
		if(!is_null($colorHandle))
		{
			imageFilledRectangle($destImage, 0, 0, $destWidth - 1, $destHeight - 1, $colorHandle);
		}

		$sx=0;$sy=0;$dx=0;$dy=0;


		//Center the scaled image
		$dx=($destWidth-$scaleWidth)/2;
		$dy=($destHeight-$scaleHeight)/2;
		if($clip&&$throttled)
		{
			$sx=($attributes[0]-$scaleWidth)/2;
			$sy=($attributes[1]-$scaleHeight)/2;
		}

		imageCopyResampled($destImage,$sourceImage,$dx,$dy,$sx,$sy,$scaleWidth,$scaleHeight,$attributes[0],$attributes[1]);
		
		switch($destType)
		{
			case IMAGETYPE_PNG:
				$ok=ImagePNG($destImage, $destPath, 9);
				break;
			case IMAGETYPE_GIF:
				$ok=Imagegif($destImage, $destPath);
				break;
			case IMAGETYPE_JPEG:
			default:
				$ok=ImageJPEG($destImage, $destPath, 80);
				break;
		}
		imagedestroy($destImage);
		return $ok;
	}
}
?>

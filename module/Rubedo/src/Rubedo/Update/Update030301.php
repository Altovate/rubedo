<?php

/**
 * Rubedo -- ECM solution
 * Copyright (c) 2014, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2014 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Update;
use Rubedo\Services\Manager;

/**
 * Methods
 * for
 * update
 * tool
 *
 * @author adobre
 *
 */
class Update030301 extends Update
{

    protected static $toVersion = '3.4.0';

    protected static $evolutionArray = [];

        /**
     * do
     * the
     * upgrade
     *
     * @return boolean
     */
    public static function upgrade()
    {
        set_time_limit(0);
        $damService=Manager::getService("Dam");
        $oldFileService = Manager::getService('Files');
        $fSManager=Manager::getService("FSManager");
        $newFileService=$fSManager->getFS();
        $existingMediaList=$damService->getList()["data"];
        foreach($existingMediaList as $media){
            try {
                if (isset($media["fields"]["originalFileId"])) {
                    $media["fields"]["originalFileId"] = static::evolveById($media["fields"]["originalFileId"], $oldFileService, $newFileService);
                }
                if (isset($media["originalFileId"])) {
                    $media["originalFileId"] = static::evolveById($media["originalFileId"], $oldFileService, $newFileService);
                }
                if (isset($media["i18n"])) {
                    foreach ($media["i18n"] as $key => &$value) {
                        if (isset($value["fields"]["originalFileId"])) {
                            $value["fields"]["originalFileId"] = static::evolveById($value["fields"]["originalFileId"], $oldFileService, $newFileService);
                        }
                    }
                }
                $damService->update($media);
            } catch (\Exception $e){

            }
        }
        $userService=Manager::getService("Users");
        $usersList=$userService->getList()["data"];
        foreach($usersList as $user){
            try {
                if (!empty($user["photo"])) {
                    $user["photo"] = static::evolveById($user["photo"], $oldFileService, $newFileService);
                    $userService->update($user);
                }
            } catch (\Exception $e) {

            }
        }
        $oldKeys=array_keys(static::$evolutionArray);
        foreach($oldKeys as $oldKey){
            try {
                $oldFileService->destroy(["id"=>$oldKey]);
            } catch (\Exception $e) {

            }
        }
        return true;
    }

    protected static function evolveById($id,$oldFS,$newFS){
        if (isset(static::$evolutionArray[$id])){
            return(static::$evolutionArray[$id]);
        } elseif ($newFS->has($id)){
            return($id);
        }
        $file=$oldFS->findById($id);
        if ($file){
            $newPathId=(string) new \MongoId();
            $newPath=$newPathId.$file->getFileName();
            $tempPath=APPLICATION_PATH . '/cache/migration'.$newPath;
            $file->write($tempPath);
            $fileResource=fopen($tempPath, 'r+');
            $result=$newFS->writeStream($newPath,$fileResource,[
                 'mimetype'=>$file->file["Content-Type"]
            ]);
            if(!$result){
                throw new \Rubedo\Exceptions\Server('Unable to upload file to new FS');
            }
            fclose($fileResource);
            static::$evolutionArray[$id]=$newPath;
            unlink($tempPath);
            return $newPath;
        }
        return null;
    }

}
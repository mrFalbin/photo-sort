<?php

class infoPhoto {
  public $id = null; // из БД
  public $oldName = null;
  public $newName_md5 = null;
  public $extension = null; // расширение
  public $oldPath = null;
  public $nameAlbum = null;
  public $newPath = null;
  public $dateCreateFile = null;
  public $dateCreatePhoto = null;
  public $yearPhoto = null;
  public $metaInfo = null;



  function __construct($id, $oldName, $newName_md5, $extension, $oldPath, $nameAlbum, $newPath, $dateCreateFile, $dateCreatePhoto, $yearPhoto, $metaInfo) {
    $this->id = $id;
    $this->oldName = $oldName;
    $this->newName_md5 = $newName_md5;
    $this->extension = $extension;
    $this->oldPath = $oldPath;
    $this->nameAlbum = $nameAlbum;
    $this->newPath = $newPath;
    $this->dateCreateFile = $dateCreateFile;
    $this->dateCreatePhoto = $dateCreatePhoto;
    $this->yearPhoto = $yearPhoto;
    $this->metaInfo = $metaInfo;
  }

}

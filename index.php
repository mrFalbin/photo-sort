<?php
ini_set('max_execution_time', 0);
date_default_timezone_set('europe/moscow');
include_once "infoPhoto.php";
$mainPathAllPhoto =  '/Volumes/Без названия/неотсортированные фотографии/остальные фото';     // Путь к неотсортированной куче фоток
$mainPathSelectedPhoto = '/Volumes/Без названия/отсортированные фоточки'; // Путь к папке, куда будут добавляться отсортированные фотки
$nameDir_dateCreateFile = 'date create file';



// --------------- Цепляемся к БД ---------------
echo "\n\n ----------------------- Подключемся к БД ----------------------- \n";
$db = new PDO('mysql:host=localhost:8889;dbname=photo_sort;charset=utf8', "root", "root");

$sql = $db->prepare("DROP TABLE photo");
if($sql->execute()) { echo "Таблица 'photo' удалена!\n"; }
else { echo "Error: Таблица 'photo' не удалена!\n"; }


$sql = $db->prepare("CREATE TABLE `photo` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `oldname` varchar(100) DEFAULT NULL,
    `newname_md5` varchar(100) DEFAULT NULL,
    `extension` varchar(10) DEFAULT NULL,
    `oldpath` varchar(255) DEFAULT NULL,
    `album` varchar(100) DEFAULT NULL,
    `newpath` varchar(255) DEFAULT NULL,
    `datecreatefile` varchar(100) DEFAULT NULL,
    `datecreatephoto` varchar(100) DEFAULT NULL,
    `metaInfo` longtext DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");
if($sql->execute()) { echo "Таблица 'photo' созданна!\n"; }
else { echo "Таблица 'photo' не создана!\n"; }
echo " ---------------------------------------------------------------- \n\n";


function addPhotoInDB($photo, $db) {
  $sql = $db->prepare("INSERT INTO photo (oldname, newname_md5, extension, oldpath, album,
                       newpath, datecreatefile, datecreatephoto, metaInfo)
                       VALUES (:oldname, :newname_md5, :extension, :oldpath, :album,
                       :newpath, :datecreatefile, :datecreatephoto, :metaInfo);");

  $sql->bindParam(':oldname', $photo->oldName, PDO::PARAM_STR);
  $sql->bindParam(':newname_md5', $photo->newName_md5, PDO::PARAM_STR);
  $sql->bindParam(':extension', $photo->extension, PDO::PARAM_STR);
  $sql->bindParam(':oldpath', $photo->oldPath, PDO::PARAM_STR);
  $sql->bindParam(':album', $photo->nameAlbum, PDO::PARAM_STR);
  $sql->bindParam(':newpath', $photo->newPath, PDO::PARAM_STR);
  $sql->bindParam(':datecreatefile', $photo->dateCreateFile, PDO::PARAM_STR);
  $sql->bindParam(':datecreatephoto', $photo->dateCreatePhoto, PDO::PARAM_STR);
  $sql->bindParam(':metaInfo', $photo->metaInfo, PDO::PARAM_STR);
  if($sql->execute()) { echo ""; } else { echo " | error | "; }
}


function updatePhotoInDB($photo, $db) {
  $sql = $db->prepare("UPDATE photo SET newpath = :newpath WHERE newname_md5 = :newname_md5");
  $sql->bindParam(':newpath', $photo->newPath, PDO::PARAM_STR);
  $sql->bindParam(':newname_md5', $photo->newName_md5, PDO::PARAM_STR);
  if($sql->execute()) { } else { echo " | error | "; }
}
// -------------------------------------------------------



// Убираем системные файлы и папки
function ignorSysFile($arrayFileAndDirectory) {
  $newArrayFileAndDirectory = [];
  for ($i=0; $i < count($arrayFileAndDirectory); $i++)
    if($arrayFileAndDirectory[$i][0] != ".")
      $newArrayFileAndDirectory[] = $arrayFileAndDirectory[$i];
  return $newArrayFileAndDirectory;
}


// Получаем массив путей всех фото из всех дирикторий
function constructionTreeFile($path, &$treeDir) {
  $files = ignorSysFile(scandir($path));
  $reg = "/^[\s\S]*\.jpg|png|jpeg|avi|mp4$/i";
  for ($i=0; $i < count($files); $i++) {
    $bufPath = $path . '/' . $files[$i];
    if(is_dir($bufPath)) constructionTreeFile($bufPath, $treeDir);
    else
      if(preg_match($reg, $files[$i]) === 1) $treeDir[] = $bufPath;
  }
}


// Функция для определения даты у фоток сделанных с моторолки L9 (дата в названии фотки)
function dateForMotorola($date) {
  $day = substr($date, 0, 2);
  $month = substr($date, 3, 2);
  $year = '20' . substr($date, 6, 2);
  return $year .'-'. $month .'-'. $day .' 00:00:00'; // 2001-03-10 17:16:18
}




$dateForEcho = time();
$allPathPhotoOld = [];
constructionTreeFile($mainPathAllPhoto, $allPathPhotoOld);
$allPhoto = [];
$arrayMD5Forsort = [];

echo "Всего фото во всех папках: ". count($allPathPhotoOld) ." \n";
echo "Добавленно фото в БД: \n";
for ($i=0; $i < count($allPathPhotoOld); $i++) {
  $path = $allPathPhotoOld[$i];
  $numberLastSlash = strrpos($path, '/');
  $bufPoz = -(strlen($path) - $numberLastSlash) + (-1);
  $numberPenultimateSlash = strrpos($path, '/', $bufPoz) + 1;
  $nameAlbum = substr($path, $numberPenultimateSlash, $numberLastSlash - $numberPenultimateSlash);
  $fullOldName = substr($path, $numberLastSlash + 1);
  $extension = substr($fullOldName, strrpos($fullOldName, '.'));
  $oldName = substr($fullOldName, 0, strlen($fullOldName) - strlen($extension));
  $md5Photo = md5_file($path);

  $metaInfo = exif_read_data($path, 0, true); //получаем мета данные
  $dateCreateFile = date('d.m.Y', filectime($path));
  $yearPhoto = null;

  // Узнаем год создания фото
  $dateCreatePhoto = $metaInfo['IFD0']['DateTime'];
  if($dateCreatePhoto == null) $dateCreatePhoto = $metaInfo['EXIF']['DateTimeDigitized'];
  if($dateCreatePhoto == null) { // если фотка сделана с моторолки L9
    $reg = "/^\d\d-\d\d-\d\d_\d\d\d\d$/"; // 21-04-10_1827
    if(preg_match($reg, $oldName) !== 0) $dateCreatePhoto = dateForMotorola($oldName);
  }

  if($dateCreatePhoto != null) {
    $yearPhoto = date('Y', strtotime($dateCreatePhoto));
    $dateCreatePhoto = date('d.m.Y', strtotime($dateCreatePhoto));
  } else { $yearPhoto = date('Y', filectime($path)); }

  $arrayMD5Forsort[] = $md5Photo;

  // записываем var_dump переменной $metaInfo в БД
  ob_start();
  var_dump($metaInfo);
  $varDump = ob_get_contents();
  ob_end_clean();

  $allPhoto[] = new infoPhoto(null, $oldName, $md5Photo, $extension, $path, $nameAlbum, null, $dateCreateFile, $dateCreatePhoto, $yearPhoto, null);
  addPhotoInDB(new infoPhoto(null, $oldName, $md5Photo, $extension, $path, $nameAlbum, null, $dateCreateFile, $dateCreatePhoto, $yearPhoto, $varDump), $db);
  echo ' | '.($i + 1);
}


echo "\n\nВсего добавленно фото в массив: ". count($allPhoto) ."\n";
$arrayMD5Forsort = array_unique($arrayMD5Forsort); // Убираем дубли
echo "Дублей: ". (count($allPhoto) - count($arrayMD5Forsort)) ."\n";
echo "Колличество фото после удаления: ". count($arrayMD5Forsort) ."\n";
echo "Выполнение этих операций заняло ". (time() - $dateForEcho) ." сек. (". floor((time() - $dateForEcho) / 60) ." мин.) \n";
$dateForEcho = time();



// Составляю новый путь для фото
function addNewPatch($infoPhoto, $mainPathSelectedPhoto, $nameDir_dateCreateFile){
  if($infoPhoto->dateCreatePhoto != null) {
    $infoPhoto->newPath = $mainPathSelectedPhoto . '/' . $infoPhoto->yearPhoto . '/' . $infoPhoto->nameAlbum .'/'. $infoPhoto->newName_md5 . $infoPhoto->extension;
  } else {
    $infoPhoto->newPath = $mainPathSelectedPhoto .'/'. $nameDir_dateCreateFile .'/'. $infoPhoto->yearPhoto .'/'. $infoPhoto->nameAlbum .'/'. $infoPhoto->newName_md5 . $infoPhoto->extension;
  }
  return $infoPhoto;
}


// Проверяем нужно ли создавать папки, если нужно - создаем
function createDir($photo, $mainPathSelectedPhoto, $nameDir_dateCreateFile) {
  if($photo->dateCreatePhoto != null) {
    $pYear  = $mainPathSelectedPhoto .'/'. $photo->yearPhoto .'/';
    if(!file_exists($pYear)) { mkdir($pYear); }
    $pAlbum = $mainPathSelectedPhoto .'/'. $photo->yearPhoto .'/'. $photo->nameAlbum .'/';
    if(!file_exists($pAlbum)) { mkdir($pAlbum); }
  // Случай когда нет данных о годе фотки, берем год создания файла
  } else {
    $pDir_dateCreateFile = $mainPathSelectedPhoto .'/'. $nameDir_dateCreateFile .'/';
    if(!file_exists($pDir_dateCreateFile)) { mkdir($pDir_dateCreateFile); }
    $pYear  = $mainPathSelectedPhoto .'/'. $nameDir_dateCreateFile .'/'. $photo->yearPhoto .'/';
    if(!file_exists($pYear)) { mkdir($pYear); }
    $pAlbum = $mainPathSelectedPhoto .'/'. $nameDir_dateCreateFile .'/'. $photo->yearPhoto .'/'. $photo->nameAlbum .'/';
    if(!file_exists($pAlbum)) { mkdir($pAlbum); }
  }
}



$selectedPhoto = [];
echo "\n ---------------------------------------------------------------- \n \n";
echo "Копируем фото: \n";
$indCopPhoto = 1;
foreach ($arrayMD5Forsort as $val) {
  for ($i=0; $i < count($allPhoto); $i++) {
    if($val == $allPhoto[$i]->newName_md5) {
      $selectedPhoto[] = addNewPatch($allPhoto[$i], $mainPathSelectedPhoto, $nameDir_dateCreateFile);
      createDir($selectedPhoto[count($selectedPhoto) - 1], $mainPathSelectedPhoto, $nameDir_dateCreateFile);
      if (!copy($selectedPhoto[count($selectedPhoto) - 1]->oldPath, $selectedPhoto[count($selectedPhoto) - 1]->newPath)) {
        echo "\n Не удалось скопировать: ". $selectedPhoto[count($selectedPhoto) - 1]->oldPath ."\n";
      }
      updatePhotoInDB($selectedPhoto[count($selectedPhoto) - 1], $db);
      echo " | $indCopPhoto";
      $indCopPhoto++;
      break;
    }
  }
}
echo "\n\nВыполнение этих операций заняло ". (time() - $dateForEcho) ." сек. (". floor((time() - $dateForEcho) / 60) ." мин.) \n";
echo "\n ---------------------------------------------------------------- \n";
echo "Ошибки: \n";
print_r(error_get_last());
echo "\n ---------------------------------------------------------------- \n";

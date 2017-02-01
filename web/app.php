<?php

/** @var \Composer\Autoload\ClassLoader $loader */
//  раскроем, что скрывается за  app/autoload.php
$loader = require __DIR__ . '/../vendor/autoload.php'; // автолоад компосера и
Doctrine\Common\Annotations\AnnotationRegistry::registerLoader([$loader, 'loadClass']);

// это кеш для продакшена, переложим и отформатируем его для изучения
// подключение их не обязательно, в продакшене делается заранее для ускорения
include_once 'bootstrap.php';
// в нем объявлены 4 контейнера (мешка/кармана , не путать с жуками, которые звучат также):
// - ParameterBag is a container for key/value pairs.
// - HeaderBag is a container for HTTP headers.
// - FileBag is a container for uploaded files.
// - ServerBag is a container for HTTP headers from the $_SERVER variable.
// и класс Request использующий выше перечисленные контейнеры
// подключается ClassCollectionLoader - Loads a list of classes and caches them in one big file.

// в режиме разрабоки можно использовать отладку
// Debug::enable();

/**
 *  composer'у объяснили откуда брать этот класс строчкой
 *
 * "autoload": {
 * ...
 * "classmap": [
 * "app/AppKernel.php",
 *
 * @param string $environment The environment  prod/dev/test
 * @param bool $debug Whether to enable debugging or not
 */
$kernel = new AppKernel('prod', false);
// в конструктуре также определяется корневая директория проекта
// и имя формируемое из названия директории

/*
 * кеширование пока опустим
 *
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();
*/

// чтобы достучаться до входных данных
$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();


/* @var \Symfony\Component\HttpFoundation\Response */
$response = $kernel->handle($request);  // магия симфони :)


// $response->send(); эквивалентно
$response->sendHeaders()   // headers, http_status, cookie
         ->sendContent();  // echo $this->content; :)

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif ('cli' !== PHP_SAPI) {
    \Symfony\Component\HttpFoundation\Response::closeOutputBuffers(0, true);
}



/*
 *  При необходимости оповещает слушателей о событии завершения
 *
 *  $this->dispatcher->dispatch(KernelEvents::TERMINATE, new PostResponseEvent($this, $request, $response));
 */
$kernel->terminate($request, $response);
// надеюсь стало немного понятнее
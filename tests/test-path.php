<?php

use WP_CLI\Path;

/**
 * Most of the code in this class was copied from or based on code in the
 * webmozart/path-util package (c) Bernhard Schussek <bschussek@gmail.com>.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Thomas Schulz <mail@king2500.net>
 */
class PathTest extends \PHPUnit_Framework_TestCase
{
    protected $storedEnv = array();

    public function setUp()
    {
        $this->storedEnv['HOME'] = getenv('HOME');
        $this->storedEnv['HOMEDRIVE'] = getenv('HOMEDRIVE');
        $this->storedEnv['HOMEPATH'] = getenv('HOMEPATH');

        putenv('HOME=/home/webmozart');
        putenv('HOMEDRIVE=');
        putenv('HOMEPATH=');
    }

    public function tearDown()
    {
        putenv('HOME='.$this->storedEnv['HOME']);
        putenv('HOMEDRIVE='.$this->storedEnv['HOMEDRIVE']);
        putenv('HOMEPATH='.$this->storedEnv['HOMEPATH']);
    }

    public function provideCanonicalizationTests()
    {
        return array(
            // relative paths (forward slash)
            array('css/./style.css', 'css/style.css'),
            array('css/../style.css', 'style.css'),
            array('css/./../style.css', 'style.css'),
            array('css/.././style.css', 'style.css'),
            array('css/../../style.css', '../style.css'),
            array('./css/style.css', 'css/style.css'),
            array('../css/style.css', '../css/style.css'),
            array('./../css/style.css', '../css/style.css'),
            array('.././css/style.css', '../css/style.css'),
            array('../../css/style.css', '../../css/style.css'),
            array('', ''),
            array('.', ''),
            array('..', '..'),
            array('./..', '..'),
            array('../.', '..'),
            array('../..', '../..'),

            // relative paths (backslash)
            array('css\\.\\style.css', 'css/style.css'),
            array('css\\..\\style.css', 'style.css'),
            array('css\\.\\..\\style.css', 'style.css'),
            array('css\\..\\.\\style.css', 'style.css'),
            array('css\\..\\..\\style.css', '../style.css'),
            array('.\\css\\style.css', 'css/style.css'),
            array('..\\css\\style.css', '../css/style.css'),
            array('.\\..\\css\\style.css', '../css/style.css'),
            array('..\\.\\css\\style.css', '../css/style.css'),
            array('..\\..\\css\\style.css', '../../css/style.css'),

            // absolute paths (forward slash, UNIX)
            array('/css/style.css', '/css/style.css'),
            array('/css/./style.css', '/css/style.css'),
            array('/css/../style.css', '/style.css'),
            array('/css/./../style.css', '/style.css'),
            array('/css/.././style.css', '/style.css'),
            array('/./css/style.css', '/css/style.css'),
            array('/../css/style.css', '/css/style.css'),
            array('/./../css/style.css', '/css/style.css'),
            array('/.././css/style.css', '/css/style.css'),
            array('/../../css/style.css', '/css/style.css'),

            // absolute paths (backslash, UNIX)
            array('\\css\\style.css', '/css/style.css'),
            array('\\css\\.\\style.css', '/css/style.css'),
            array('\\css\\..\\style.css', '/style.css'),
            array('\\css\\.\\..\\style.css', '/style.css'),
            array('\\css\\..\\.\\style.css', '/style.css'),
            array('\\.\\css\\style.css', '/css/style.css'),
            array('\\..\\css\\style.css', '/css/style.css'),
            array('\\.\\..\\css\\style.css', '/css/style.css'),
            array('\\..\\.\\css\\style.css', '/css/style.css'),
            array('\\..\\..\\css\\style.css', '/css/style.css'),

            // absolute paths (forward slash, Windows)
            array('C:/css/style.css', 'C:/css/style.css'),
            array('C:/css/./style.css', 'C:/css/style.css'),
            array('C:/css/../style.css', 'C:/style.css'),
            array('C:/css/./../style.css', 'C:/style.css'),
            array('C:/css/.././style.css', 'C:/style.css'),
            array('C:/./css/style.css', 'C:/css/style.css'),
            array('C:/../css/style.css', 'C:/css/style.css'),
            array('C:/./../css/style.css', 'C:/css/style.css'),
            array('C:/.././css/style.css', 'C:/css/style.css'),
            array('C:/../../css/style.css', 'C:/css/style.css'),

            // absolute paths (backslash, Windows)
            array('C:\\css\\style.css', 'C:/css/style.css'),
            array('C:\\css\\.\\style.css', 'C:/css/style.css'),
            array('C:\\css\\..\\style.css', 'C:/style.css'),
            array('C:\\css\\.\\..\\style.css', 'C:/style.css'),
            array('C:\\css\\..\\.\\style.css', 'C:/style.css'),
            array('C:\\.\\css\\style.css', 'C:/css/style.css'),
            array('C:\\..\\css\\style.css', 'C:/css/style.css'),
            array('C:\\.\\..\\css\\style.css', 'C:/css/style.css'),
            array('C:\\..\\.\\css\\style.css', 'C:/css/style.css'),
            array('C:\\..\\..\\css\\style.css', 'C:/css/style.css'),

            // Windows special case
            array('C:', 'C:/'),

            // Don't change malformed path
            array('C:css/style.css', 'C:css/style.css'),

            // absolute paths (stream, UNIX)
            array('phar:///css/style.css', 'phar:///css/style.css'),
            array('phar:///css/./style.css', 'phar:///css/style.css'),
            array('phar:///css/../style.css', 'phar:///style.css'),
            array('phar:///css/./../style.css', 'phar:///style.css'),
            array('phar:///css/.././style.css', 'phar:///style.css'),
            array('phar:///./css/style.css', 'phar:///css/style.css'),
            array('phar:///../css/style.css', 'phar:///css/style.css'),
            array('phar:///./../css/style.css', 'phar:///css/style.css'),
            array('phar:///.././css/style.css', 'phar:///css/style.css'),
            array('phar:///../../css/style.css', 'phar:///css/style.css'),

            // absolute paths (stream, Windows)
            array('phar://C:/css/style.css', 'phar://C:/css/style.css'),
            array('phar://C:/css/./style.css', 'phar://C:/css/style.css'),
            array('phar://C:/css/../style.css', 'phar://C:/style.css'),
            array('phar://C:/css/./../style.css', 'phar://C:/style.css'),
            array('phar://C:/css/.././style.css', 'phar://C:/style.css'),
            array('phar://C:/./css/style.css', 'phar://C:/css/style.css'),
            array('phar://C:/../css/style.css', 'phar://C:/css/style.css'),
            array('phar://C:/./../css/style.css', 'phar://C:/css/style.css'),
            array('phar://C:/.././css/style.css', 'phar://C:/css/style.css'),
            array('phar://C:/../../css/style.css', 'phar://C:/css/style.css'),

            // paths with "~" UNIX
            array('~/css/style.css', '/home/webmozart/css/style.css'),
            array('~/css/./style.css', '/home/webmozart/css/style.css'),
            array('~/css/../style.css', '/home/webmozart/style.css'),
            array('~/css/./../style.css', '/home/webmozart/style.css'),
            array('~/css/.././style.css', '/home/webmozart/style.css'),
            array('~/./css/style.css', '/home/webmozart/css/style.css'),
            array('~/../css/style.css', '/home/css/style.css'),
            array('~/./../css/style.css', '/home/css/style.css'),
            array('~/.././css/style.css', '/home/css/style.css'),
            array('~/../../css/style.css', '/css/style.css'),
        );
    }

    /**
     * @dataProvider provideCanonicalizationTests
     */
    public function testCanonicalize($path, $canonicalized)
    {
        $this->assertSame($canonicalized, Path::canonicalize($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testCanonicalizeFailsIfInvalidPath()
    {
        Path::canonicalize(array());
    }

    public function provideGetDirectoryTests()
    {
        return array(
            array('/webmozart/puli/style.css', '/webmozart/puli'),
            array('/webmozart/puli', '/webmozart'),
            array('/webmozart', '/'),
            array('/', '/'),
            array('', ''),

            array('\\webmozart\\puli\\style.css', '/webmozart/puli'),
            array('\\webmozart\\puli', '/webmozart'),
            array('\\webmozart', '/'),
            array('\\', '/'),

            array('C:/webmozart/puli/style.css', 'C:/webmozart/puli'),
            array('C:/webmozart/puli', 'C:/webmozart'),
            array('C:/webmozart', 'C:/'),
            array('C:/', 'C:/'),
            array('C:', 'C:/'),

            array('C:\\webmozart\\puli\\style.css', 'C:/webmozart/puli'),
            array('C:\\webmozart\\puli', 'C:/webmozart'),
            array('C:\\webmozart', 'C:/'),
            array('C:\\', 'C:/'),

            array('phar:///webmozart/puli/style.css', 'phar:///webmozart/puli'),
            array('phar:///webmozart/puli', 'phar:///webmozart'),
            array('phar:///webmozart', 'phar:///'),
            array('phar:///', 'phar:///'),

            array('phar://C:/webmozart/puli/style.css', 'phar://C:/webmozart/puli'),
            array('phar://C:/webmozart/puli', 'phar://C:/webmozart'),
            array('phar://C:/webmozart', 'phar://C:/'),
            array('phar://C:/', 'phar://C:/'),

            array('webmozart/puli/style.css', 'webmozart/puli'),
            array('webmozart/puli', 'webmozart'),
            array('webmozart', ''),

            array('webmozart\\puli\\style.css', 'webmozart/puli'),
            array('webmozart\\puli', 'webmozart'),
            array('webmozart', ''),

            array('/webmozart/./puli/style.css', '/webmozart/puli'),
            array('/webmozart/../puli/style.css', '/puli'),
            array('/webmozart/./../puli/style.css', '/puli'),
            array('/webmozart/.././puli/style.css', '/puli'),
            array('/webmozart/../../puli/style.css', '/puli'),
            array('/.', '/'),
            array('/..', '/'),

            array('C:webmozart', ''),
        );
    }

    /**
     * @dataProvider provideGetDirectoryTests
     */
    public function testGetDirectory($path, $directory)
    {
        $this->assertSame($directory, Path::get_directory($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testGetDirectoryFailsIfInvalidPath()
    {
        Path::get_directory(array());
    }

    public function provideGetFilenameTests()
    {
        return array(
            array('/webmozart/puli/style.css', 'style.css'),
            array('/webmozart/puli/STYLE.CSS', 'STYLE.CSS'),
            array('/webmozart/puli/style.css/', 'style.css'),
            array('/webmozart/puli/', 'puli'),
            array('/webmozart/puli', 'puli'),
            array('/', ''),
            array('', ''),
        );
    }

    /**
     * @dataProvider provideGetFilenameTests
     */
    public function testGetFilename($path, $filename)
    {
        $this->assertSame($filename, Path::get_filename($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testGetFilenameFailsIfInvalidPath()
    {
        Path::get_filename(array());
    }

    public function provideGetFilenameWithoutExtensionTests()
    {
        return array(
            array('/webmozart/puli/style.css.twig', null, 'style.css'),
            array('/webmozart/puli/style.css.', null, 'style.css'),
            array('/webmozart/puli/style.css', null, 'style'),
            array('/webmozart/puli/.style.css', null, '.style'),
            array('/webmozart/puli/', null, 'puli'),
            array('/webmozart/puli', null, 'puli'),
            array('/', null, ''),
            array('', null, ''),

            array('/webmozart/puli/style.css', 'css', 'style'),
            array('/webmozart/puli/style.css', '.css', 'style'),
            array('/webmozart/puli/style.css', 'twig', 'style.css'),
            array('/webmozart/puli/style.css', '.twig', 'style.css'),
            array('/webmozart/puli/style.css', '', 'style.css'),
            array('/webmozart/puli/style.css.', '', 'style.css'),
            array('/webmozart/puli/style.css.', '.', 'style.css'),
            array('/webmozart/puli/style.css.', '.css', 'style.css'),
            array('/webmozart/puli/.style.css', 'css', '.style'),
            array('/webmozart/puli/.style.css', '.css', '.style'),
        );
    }

    /**
     * @dataProvider provideGetFilenameWithoutExtensionTests
     */
    public function testGetFilenameWithoutExtension($path, $extension, $filename)
    {
        $this->assertSame($filename, Path::get_filename_without_extension($path, $extension));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testGetFilenameWithoutExtensionFailsIfInvalidPath()
    {
        Path::get_filename_without_extension(array(), '.css');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The extension must be a string or null. Got: array
     */
    public function testGetFilenameWithoutExtensionFailsIfInvalidExtension()
    {
        Path::get_filename_without_extension('/style.css', array());
    }

    public function provideGetExtensionTests()
    {
        $tests = array(
            array('/webmozart/puli/style.css.twig', false, 'twig'),
            array('/webmozart/puli/style.css', false, 'css'),
            array('/webmozart/puli/style.css.', false, ''),
            array('/webmozart/puli/', false, ''),
            array('/webmozart/puli', false, ''),
            array('/', false, ''),
            array('', false, ''),

            array('/webmozart/puli/style.CSS', false, 'CSS'),
            array('/webmozart/puli/style.CSS', true, 'css'),
            array('/webmozart/puli/style.ÄÖÜ', false, 'ÄÖÜ'),
        );

        if (extension_loaded('mbstring')) {
            // This can only be tested, when mbstring is installed
            $tests[] = array('/webmozart/puli/style.ÄÖÜ', true, 'äöü');
        }

        return $tests;
    }

    /**
     * @dataProvider provideGetExtensionTests
     */
    public function testGetExtension($path, $forceLowerCase, $extension)
    {
        $this->assertSame($extension, Path::get_extension($path, $forceLowerCase));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testGetExtensionFailsIfInvalidPath()
    {
        Path::get_extension(array());
    }

    public function provideHasExtensionTests()
    {
        $tests = array(
            array(true, '/webmozart/puli/style.css.twig', null, false),
            array(true, '/webmozart/puli/style.css', null, false),
            array(false, '/webmozart/puli/style.css.', null, false),
            array(false, '/webmozart/puli/', null, false),
            array(false, '/webmozart/puli', null, false),
            array(false, '/', null, false),
            array(false, '', null, false),

            array(true, '/webmozart/puli/style.css.twig', 'twig', false),
            array(false, '/webmozart/puli/style.css.twig', 'css', false),
            array(true, '/webmozart/puli/style.css', 'css', false),
            array(true, '/webmozart/puli/style.css', '.css', false),
            array(true, '/webmozart/puli/style.css.', '', false),
            array(false, '/webmozart/puli/', 'ext', false),
            array(false, '/webmozart/puli', 'ext', false),
            array(false, '/', 'ext', false),
            array(false, '', 'ext', false),

            array(false, '/webmozart/puli/style.css', 'CSS', false),
            array(true, '/webmozart/puli/style.css', 'CSS', true),
            array(false, '/webmozart/puli/style.CSS', 'css', false),
            array(true, '/webmozart/puli/style.CSS', 'css', true),
            array(true, '/webmozart/puli/style.ÄÖÜ', 'ÄÖÜ', false),

            array(true, '/webmozart/puli/style.css', array('ext', 'css'), false),
            array(true, '/webmozart/puli/style.css', array('.ext', '.css'), false),
            array(true, '/webmozart/puli/style.css.', array('ext', ''), false),
            array(false, '/webmozart/puli/style.css', array('foo', 'bar', ''), false),
            array(false, '/webmozart/puli/style.css', array('.foo', '.bar', ''), false),
        );

        if (extension_loaded('mbstring')) {
            // This can only be tested, when mbstring is installed
            $tests[] = array(true, '/webmozart/puli/style.ÄÖÜ', 'äöü', true);
            $tests[] = array(true, '/webmozart/puli/style.ÄÖÜ', array('äöü'), true);
        }

        return $tests;
    }

    /**
     * @dataProvider provideHasExtensionTests
     */
    public function testHasExtension($hasExtension, $path, $extension, $ignoreCase)
    {
        $this->assertSame($hasExtension, Path::has_extension($path, $extension, $ignoreCase));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testHasExtensionFailsIfInvalidPath()
    {
        Path::has_extension(array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The extensions must be strings. Got: stdClass
     */
    public function testHasExtensionFailsIfInvalidExtension()
    {
        Path::has_extension('/style.css', (object) array());
    }

    public function provideChangeExtensionTests()
    {
        return array(
            array('/webmozart/puli/style.css.twig', 'html', '/webmozart/puli/style.css.html'),
            array('/webmozart/puli/style.css', 'sass', '/webmozart/puli/style.sass'),
            array('/webmozart/puli/style.css', '.sass', '/webmozart/puli/style.sass'),
            array('/webmozart/puli/style.css', '', '/webmozart/puli/style.'),
            array('/webmozart/puli/style.css.', 'twig', '/webmozart/puli/style.css.twig'),
            array('/webmozart/puli/style.css.', '', '/webmozart/puli/style.css.'),
            array('/webmozart/puli/style.css', 'äöü', '/webmozart/puli/style.äöü'),
            array('/webmozart/puli/style.äöü', 'css', '/webmozart/puli/style.css'),
            array('/webmozart/puli/', 'css', '/webmozart/puli/'),
            array('/webmozart/puli', 'css', '/webmozart/puli.css'),
            array('/', 'css', '/'),
            array('', 'css', ''),
        );
    }

    /**
     * @dataProvider provideChangeExtensionTests
     */
    public function testChangeExtension($path, $extension, $pathExpected)
    {
        static $call = 0;
        $this->assertSame($pathExpected, Path::change_extension($path, $extension));
        ++$call;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testChangeExtensionFailsIfInvalidPath()
    {
        Path::change_extension(array(), '.sass');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The extension must be a string. Got: array
     */
    public function testChangeExtensionFailsIfInvalidExtension()
    {
        Path::change_extension('/style.css', array());
    }

    public function provideIsAbsolutePathTests()
    {
        return array(
            array('/css/style.css', true),
            array('/', true),
            array('css/style.css', false),
            array('', false),

            array('\\css\\style.css', true),
            array('\\', true),
            array('css\\style.css', false),

            array('C:/css/style.css', true),
            array('D:/', true),

            array('E:\\css\\style.css', true),
            array('F:\\', true),

            array('phar:///css/style.css', true),
            array('phar:///', true),

            // Windows special case
            array('C:', true),

            // Not considered absolute
            array('C:css/style.css', false),
        );
    }

    /**
     * @dataProvider provideIsAbsolutePathTests
     */
    public function testIsAbsolute($path, $isAbsolute)
    {
        $this->assertSame($isAbsolute, Path::is_absolute($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testIsAbsoluteFailsIfInvalidPath()
    {
        Path::is_absolute(array());
    }

    /**
     * @dataProvider provideIsAbsolutePathTests
     */
    public function testIsRelative($path, $isAbsolute)
    {
        $this->assertSame(!$isAbsolute, Path::is_relative($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testIsRelativeFailsIfInvalidPath()
    {
        Path::is_relative(array());
    }

    public function provideGetRootTests()
    {
        return array(
            array('/css/style.css', '/'),
            array('/', '/'),
            array('css/style.css', ''),
            array('', ''),

            array('\\css\\style.css', '/'),
            array('\\', '/'),
            array('css\\style.css', ''),

            array('C:/css/style.css', 'C:/'),
            array('C:/', 'C:/'),
            array('C:', 'C:/'),

            array('D:\\css\\style.css', 'D:/'),
            array('D:\\', 'D:/'),

            array('phar:///css/style.css', 'phar:///'),
            array('phar:///', 'phar:///'),

            array('phar://C:/css/style.css', 'phar://C:/'),
            array('phar://C:/', 'phar://C:/'),
            array('phar://C:', 'phar://C:/'),
        );
    }

    /**
     * @dataProvider provideGetRootTests
     */
    public function testGetRoot($path, $root)
    {
        $this->assertSame($root, Path::get_root($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testGetRootFailsIfInvalidPath()
    {
        Path::get_root(array());
    }

    public function providePathTests()
    {
        return array(
            // relative to absolute path
            array('css/style.css', '/webmozart/puli', '/webmozart/puli/css/style.css'),
            array('../css/style.css', '/webmozart/puli', '/webmozart/css/style.css'),
            array('../../css/style.css', '/webmozart/puli', '/css/style.css'),

            // relative to root
            array('css/style.css', '/', '/css/style.css'),
            array('css/style.css', 'C:', 'C:/css/style.css'),
            array('css/style.css', 'C:/', 'C:/css/style.css'),

            // same sub directories in different base directories
            array('../../puli/css/style.css', '/webmozart/css', '/puli/css/style.css'),

            array('', '/webmozart/puli', '/webmozart/puli'),
            array('..', '/webmozart/puli', '/webmozart'),
        );
    }

    public function provideMakeAbsoluteTests()
    {
        return array_merge($this->providePathTests(), array(
            // collapse dots
            array('css/./style.css', '/webmozart/puli', '/webmozart/puli/css/style.css'),
            array('css/../style.css', '/webmozart/puli', '/webmozart/puli/style.css'),
            array('css/./../style.css', '/webmozart/puli', '/webmozart/puli/style.css'),
            array('css/.././style.css', '/webmozart/puli', '/webmozart/puli/style.css'),
            array('./css/style.css', '/webmozart/puli', '/webmozart/puli/css/style.css'),

            array('css\\.\\style.css', '\\webmozart\\puli', '/webmozart/puli/css/style.css'),
            array('css\\..\\style.css', '\\webmozart\\puli', '/webmozart/puli/style.css'),
            array('css\\.\\..\\style.css', '\\webmozart\\puli', '/webmozart/puli/style.css'),
            array('css\\..\\.\\style.css', '\\webmozart\\puli', '/webmozart/puli/style.css'),
            array('.\\css\\style.css', '\\webmozart\\puli', '/webmozart/puli/css/style.css'),

            // collapse dots on root
            array('./css/style.css', '/', '/css/style.css'),
            array('../css/style.css', '/', '/css/style.css'),
            array('../css/./style.css', '/', '/css/style.css'),
            array('../css/../style.css', '/', '/style.css'),
            array('../css/./../style.css', '/', '/style.css'),
            array('../css/.././style.css', '/', '/style.css'),

            array('.\\css\\style.css', '\\', '/css/style.css'),
            array('..\\css\\style.css', '\\', '/css/style.css'),
            array('..\\css\\.\\style.css', '\\', '/css/style.css'),
            array('..\\css\\..\\style.css', '\\', '/style.css'),
            array('..\\css\\.\\..\\style.css', '\\', '/style.css'),
            array('..\\css\\..\\.\\style.css', '\\', '/style.css'),

            array('./css/style.css', 'C:/', 'C:/css/style.css'),
            array('../css/style.css', 'C:/', 'C:/css/style.css'),
            array('../css/./style.css', 'C:/', 'C:/css/style.css'),
            array('../css/../style.css', 'C:/', 'C:/style.css'),
            array('../css/./../style.css', 'C:/', 'C:/style.css'),
            array('../css/.././style.css', 'C:/', 'C:/style.css'),

            array('.\\css\\style.css', 'C:\\', 'C:/css/style.css'),
            array('..\\css\\style.css', 'C:\\', 'C:/css/style.css'),
            array('..\\css\\.\\style.css', 'C:\\', 'C:/css/style.css'),
            array('..\\css\\..\\style.css', 'C:\\', 'C:/style.css'),
            array('..\\css\\.\\..\\style.css', 'C:\\', 'C:/style.css'),
            array('..\\css\\..\\.\\style.css', 'C:\\', 'C:/style.css'),

            array('./css/style.css', 'phar:///', 'phar:///css/style.css'),
            array('../css/style.css', 'phar:///', 'phar:///css/style.css'),
            array('../css/./style.css', 'phar:///', 'phar:///css/style.css'),
            array('../css/../style.css', 'phar:///', 'phar:///style.css'),
            array('../css/./../style.css', 'phar:///', 'phar:///style.css'),
            array('../css/.././style.css', 'phar:///', 'phar:///style.css'),

            array('./css/style.css', 'phar://C:/', 'phar://C:/css/style.css'),
            array('../css/style.css', 'phar://C:/', 'phar://C:/css/style.css'),
            array('../css/./style.css', 'phar://C:/', 'phar://C:/css/style.css'),
            array('../css/../style.css', 'phar://C:/', 'phar://C:/style.css'),
            array('../css/./../style.css', 'phar://C:/', 'phar://C:/style.css'),
            array('../css/.././style.css', 'phar://C:/', 'phar://C:/style.css'),

            // absolute paths
            array('/css/style.css', '/webmozart/puli', '/css/style.css'),
            array('\\css\\style.css', '/webmozart/puli', '/css/style.css'),
            array('C:/css/style.css', 'C:/webmozart/puli', 'C:/css/style.css'),
            array('D:\\css\\style.css', 'D:/webmozart/puli', 'D:/css/style.css'),
        ));
    }

    /**
     * @dataProvider provideMakeAbsoluteTests
     */
    public function testMakeAbsolute($relativePath, $basePath, $absolutePath)
    {
        $this->assertSame($absolutePath, Path::make_absolute($relativePath, $basePath));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testMakeAbsoluteFailsIfInvalidPath()
    {
        Path::make_absolute(array(), '/webmozart/puli');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a non-empty string. Got: array
     */
    public function testMakeAbsoluteFailsIfInvalidBasePath()
    {
        Path::make_absolute('css/style.css', array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path "webmozart/puli" is not an absolute path.
     */
    public function testMakeAbsoluteFailsIfBasePathNotAbsolute()
    {
        Path::make_absolute('css/style.css', 'webmozart/puli');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a non-empty string. Got: ""
     */
    public function testMakeAbsoluteFailsIfBasePathEmpty()
    {
        Path::make_absolute('css/style.css', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a non-empty string. Got: NULL
     */
    public function testMakeAbsoluteFailsIfBasePathNull()
    {
        Path::make_absolute('css/style.css', null);
    }

    public function provideAbsolutePathsWithDifferentRoots()
    {
        return array(
            array('C:/css/style.css', '/webmozart/puli'),
            array('C:/css/style.css', '\\webmozart\\puli'),
            array('C:\\css\\style.css', '/webmozart/puli'),
            array('C:\\css\\style.css', '\\webmozart\\puli'),

            array('/css/style.css', 'C:/webmozart/puli'),
            array('/css/style.css', 'C:\\webmozart\\puli'),
            array('\\css\\style.css', 'C:/webmozart/puli'),
            array('\\css\\style.css', 'C:\\webmozart\\puli'),

            array('D:/css/style.css', 'C:/webmozart/puli'),
            array('D:/css/style.css', 'C:\\webmozart\\puli'),
            array('D:\\css\\style.css', 'C:/webmozart/puli'),
            array('D:\\css\\style.css', 'C:\\webmozart\\puli'),

            array('phar:///css/style.css', '/webmozart/puli'),
            array('/css/style.css', 'phar:///webmozart/puli'),

            array('phar://C:/css/style.css', 'C:/webmozart/puli'),
            array('phar://C:/css/style.css', 'C:\\webmozart\\puli'),
            array('phar://C:\\css\\style.css', 'C:/webmozart/puli'),
            array('phar://C:\\css\\style.css', 'C:\\webmozart\\puli'),
        );
    }

    /**
     * @dataProvider provideAbsolutePathsWithDifferentRoots
     */
    public function testMakeAbsoluteDoesNotFailIfDifferentRoot($basePath, $absolutePath)
    {
        // If a path in partition D: is passed, but $basePath is in partition
        // C:, the path should be returned unchanged
        $this->assertSame(Path::canonicalize($absolutePath), Path::make_absolute($absolutePath, $basePath));
    }

    public function provideMakeRelativeTests()
    {
        $paths = array_map(function (array $arguments) {
            return array($arguments[2], $arguments[1], $arguments[0]);
        }, $this->providePathTests());

        return array_merge($paths, array(
            array('/webmozart/puli/./css/style.css', '/webmozart/puli', 'css/style.css'),
            array('/webmozart/puli/../css/style.css', '/webmozart/puli', '../css/style.css'),
            array('/webmozart/puli/.././css/style.css', '/webmozart/puli', '../css/style.css'),
            array('/webmozart/puli/./../css/style.css', '/webmozart/puli', '../css/style.css'),
            array('/webmozart/puli/../../css/style.css', '/webmozart/puli', '../../css/style.css'),
            array('/webmozart/puli/css/style.css', '/webmozart/./puli', 'css/style.css'),
            array('/webmozart/puli/css/style.css', '/webmozart/../puli', '../webmozart/puli/css/style.css'),
            array('/webmozart/puli/css/style.css', '/webmozart/./../puli', '../webmozart/puli/css/style.css'),
            array('/webmozart/puli/css/style.css', '/webmozart/.././puli', '../webmozart/puli/css/style.css'),
            array('/webmozart/puli/css/style.css', '/webmozart/../../puli', '../webmozart/puli/css/style.css'),

            // first argument shorter than second
            array('/css', '/webmozart/puli', '../../css'),

            // second argument shorter than first
            array('/webmozart/puli', '/css', '../webmozart/puli'),

            array('\\webmozart\\puli\\css\\style.css', '\\webmozart\\puli', 'css/style.css'),
            array('\\webmozart\\css\\style.css', '\\webmozart\\puli', '../css/style.css'),
            array('\\css\\style.css', '\\webmozart\\puli', '../../css/style.css'),

            array('C:/webmozart/puli/css/style.css', 'C:/webmozart/puli', 'css/style.css'),
            array('C:/webmozart/css/style.css', 'C:/webmozart/puli', '../css/style.css'),
            array('C:/css/style.css', 'C:/webmozart/puli', '../../css/style.css'),

            array('C:\\webmozart\\puli\\css\\style.css', 'C:\\webmozart\\puli', 'css/style.css'),
            array('C:\\webmozart\\css\\style.css', 'C:\\webmozart\\puli', '../css/style.css'),
            array('C:\\css\\style.css', 'C:\\webmozart\\puli', '../../css/style.css'),

            array('phar:///webmozart/puli/css/style.css', 'phar:///webmozart/puli', 'css/style.css'),
            array('phar:///webmozart/css/style.css', 'phar:///webmozart/puli', '../css/style.css'),
            array('phar:///css/style.css', 'phar:///webmozart/puli', '../../css/style.css'),

            array('phar://C:/webmozart/puli/css/style.css', 'phar://C:/webmozart/puli', 'css/style.css'),
            array('phar://C:/webmozart/css/style.css', 'phar://C:/webmozart/puli', '../css/style.css'),
            array('phar://C:/css/style.css', 'phar://C:/webmozart/puli', '../../css/style.css'),

            // already relative + already in root basepath
            array('../style.css', '/', 'style.css'),
            array('./style.css', '/', 'style.css'),
            array('../../style.css', '/', 'style.css'),
            array('..\\style.css', 'C:\\', 'style.css'),
            array('.\\style.css', 'C:\\', 'style.css'),
            array('..\\..\\style.css', 'C:\\', 'style.css'),
            array('../style.css', 'C:/', 'style.css'),
            array('./style.css', 'C:/', 'style.css'),
            array('../../style.css', 'C:/', 'style.css'),
            array('..\\style.css', '\\', 'style.css'),
            array('.\\style.css', '\\', 'style.css'),
            array('..\\..\\style.css', '\\', 'style.css'),
            array('../style.css', 'phar:///', 'style.css'),
            array('./style.css', 'phar:///', 'style.css'),
            array('../../style.css', 'phar:///', 'style.css'),
            array('..\\style.css', 'phar://C:\\', 'style.css'),
            array('.\\style.css', 'phar://C:\\', 'style.css'),
            array('..\\..\\style.css', 'phar://C:\\', 'style.css'),

            array('css/../style.css', '/', 'style.css'),
            array('css/./style.css', '/', 'css/style.css'),
            array('css\\..\\style.css', 'C:\\', 'style.css'),
            array('css\\.\\style.css', 'C:\\', 'css/style.css'),
            array('css/../style.css', 'C:/', 'style.css'),
            array('css/./style.css', 'C:/', 'css/style.css'),
            array('css\\..\\style.css', '\\', 'style.css'),
            array('css\\.\\style.css', '\\', 'css/style.css'),
            array('css/../style.css', 'phar:///', 'style.css'),
            array('css/./style.css', 'phar:///', 'css/style.css'),
            array('css\\..\\style.css', 'phar://C:\\', 'style.css'),
            array('css\\.\\style.css', 'phar://C:\\', 'css/style.css'),

            // already relative
            array('css/style.css', '/webmozart/puli', 'css/style.css'),
            array('css\\style.css', '\\webmozart\\puli', 'css/style.css'),

            // both relative
            array('css/style.css', 'webmozart/puli', '../../css/style.css'),
            array('css\\style.css', 'webmozart\\puli', '../../css/style.css'),

            // relative to empty
            array('css/style.css', '', 'css/style.css'),
            array('css\\style.css', '', 'css/style.css'),

            // different slashes in path and base path
            array('/webmozart/puli/css/style.css', '\\webmozart\\puli', 'css/style.css'),
            array('\\webmozart\\puli\\css\\style.css', '/webmozart/puli', 'css/style.css'),
        ));
    }

    /**
     * @dataProvider provideMakeRelativeTests
     */
    public function testMakeRelative($absolutePath, $basePath, $relativePath)
    {
        $this->assertSame($relativePath, Path::make_relative($absolutePath, $basePath));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testMakeRelativeFailsIfInvalidPath()
    {
        Path::make_relative(array(), '/webmozart/puli');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a string. Got: array
     */
    public function testMakeRelativeFailsIfInvalidBasePath()
    {
        Path::make_relative('/webmozart/puli/css/style.css', array());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The absolute path "/webmozart/puli/css/style.css" cannot be made relative to the relative path "webmozart/puli". You should provide an absolute base path instead.
     */
    public function testMakeRelativeFailsIfAbsolutePathAndBasePathNotAbsolute()
    {
        Path::make_relative('/webmozart/puli/css/style.css', 'webmozart/puli');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The absolute path "/webmozart/puli/css/style.css" cannot be made relative to the relative path "". You should provide an absolute base path instead.
     */
    public function testMakeRelativeFailsIfAbsolutePathAndBasePathEmpty()
    {
        Path::make_relative('/webmozart/puli/css/style.css', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a string. Got: NULL
     */
    public function testMakeRelativeFailsIfBasePathNull()
    {
        Path::make_relative('/webmozart/puli/css/style.css', null);
    }

    /**
     * @dataProvider provideAbsolutePathsWithDifferentRoots
     * @expectedException \InvalidArgumentException
     */
    public function testMakeRelativeFailsIfDifferentRoot($absolutePath, $basePath)
    {
        Path::make_relative($absolutePath, $basePath);
    }

    public function provideIsLocalTests()
    {
        return array(
            array('/bg.png', true),
            array('bg.png', true),
            array('http://example.com/bg.png', false),
            array('http://example.com', false),
            array('', false),
        );
    }

    /**
     * @dataProvider provideIsLocalTests
     */
    public function testIsLocal($path, $isLocal)
    {
        $this->assertSame($isLocal, Path::is_local($path));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testIsLocalFailsIfInvalidPath()
    {
        Path::is_local(array());
    }

    public function provideGetLongestCommonBasePathTests()
    {
        return array(
            // same paths
            array(array('/base/path', '/base/path'), '/base/path'),
            array(array('C:/base/path', 'C:/base/path'), 'C:/base/path'),
            array(array('C:\\base\\path', 'C:\\base\\path'), 'C:/base/path'),
            array(array('C:/base/path', 'C:\\base\\path'), 'C:/base/path'),
            array(array('phar:///base/path', 'phar:///base/path'), 'phar:///base/path'),
            array(array('phar://C:/base/path', 'phar://C:/base/path'), 'phar://C:/base/path'),

            // trailing slash
            array(array('/base/path/', '/base/path'), '/base/path'),
            array(array('C:/base/path/', 'C:/base/path'), 'C:/base/path'),
            array(array('C:\\base\\path\\', 'C:\\base\\path'), 'C:/base/path'),
            array(array('C:/base/path/', 'C:\\base\\path'), 'C:/base/path'),
            array(array('phar:///base/path/', 'phar:///base/path'), 'phar:///base/path'),
            array(array('phar://C:/base/path/', 'phar://C:/base/path'), 'phar://C:/base/path'),

            array(array('/base/path', '/base/path/'), '/base/path'),
            array(array('C:/base/path', 'C:/base/path/'), 'C:/base/path'),
            array(array('C:\\base\\path', 'C:\\base\\path\\'), 'C:/base/path'),
            array(array('C:/base/path', 'C:\\base\\path\\'), 'C:/base/path'),
            array(array('phar:///base/path', 'phar:///base/path/'), 'phar:///base/path'),
            array(array('phar://C:/base/path', 'phar://C:/base/path/'), 'phar://C:/base/path'),

            // first in second
            array(array('/base/path/sub', '/base/path'), '/base/path'),
            array(array('C:/base/path/sub', 'C:/base/path'), 'C:/base/path'),
            array(array('C:\\base\\path\\sub', 'C:\\base\\path'), 'C:/base/path'),
            array(array('C:/base/path/sub', 'C:\\base\\path'), 'C:/base/path'),
            array(array('phar:///base/path/sub', 'phar:///base/path'), 'phar:///base/path'),
            array(array('phar://C:/base/path/sub', 'phar://C:/base/path'), 'phar://C:/base/path'),

            // second in first
            array(array('/base/path', '/base/path/sub'), '/base/path'),
            array(array('C:/base/path', 'C:/base/path/sub'), 'C:/base/path'),
            array(array('C:\\base\\path', 'C:\\base\\path\\sub'), 'C:/base/path'),
            array(array('C:/base/path', 'C:\\base\\path\\sub'), 'C:/base/path'),
            array(array('phar:///base/path', 'phar:///base/path/sub'), 'phar:///base/path'),
            array(array('phar://C:/base/path', 'phar://C:/base/path/sub'), 'phar://C:/base/path'),

            // first is prefix
            array(array('/base/path/di', '/base/path/dir'), '/base/path'),
            array(array('C:/base/path/di', 'C:/base/path/dir'), 'C:/base/path'),
            array(array('C:\\base\\path\\di', 'C:\\base\\path\\dir'), 'C:/base/path'),
            array(array('C:/base/path/di', 'C:\\base\\path\\dir'), 'C:/base/path'),
            array(array('phar:///base/path/di', 'phar:///base/path/dir'), 'phar:///base/path'),
            array(array('phar://C:/base/path/di', 'phar://C:/base/path/dir'), 'phar://C:/base/path'),

            // second is prefix
            array(array('/base/path/dir', '/base/path/di'), '/base/path'),
            array(array('C:/base/path/dir', 'C:/base/path/di'), 'C:/base/path'),
            array(array('C:\\base\\path\\dir', 'C:\\base\\path\\di'), 'C:/base/path'),
            array(array('C:/base/path/dir', 'C:\\base\\path\\di'), 'C:/base/path'),
            array(array('phar:///base/path/dir', 'phar:///base/path/di'), 'phar:///base/path'),
            array(array('phar://C:/base/path/dir', 'phar://C:/base/path/di'), 'phar://C:/base/path'),

            // root is common base path
            array(array('/first', '/second'), '/'),
            array(array('C:/first', 'C:/second'), 'C:/'),
            array(array('C:\\first', 'C:\\second'), 'C:/'),
            array(array('C:/first', 'C:\\second'), 'C:/'),
            array(array('phar:///first', 'phar:///second'), 'phar:///'),
            array(array('phar://C:/first', 'phar://C:/second'), 'phar://C:/'),

            // windows vs unix
            array(array('/base/path', 'C:/base/path'), null),
            array(array('C:/base/path', '/base/path'), null),
            array(array('/base/path', 'C:\\base\\path'), null),
            array(array('phar:///base/path', 'phar://C:/base/path'), null),

            // different partitions
            array(array('C:/base/path', 'D:/base/path'), null),
            array(array('C:/base/path', 'D:\\base\\path'), null),
            array(array('C:\\base\\path', 'D:\\base\\path'), null),
            array(array('phar://C:/base/path', 'phar://D:/base/path'), null),

            // three paths
            array(array('/base/path/foo', '/base/path', '/base/path/bar'), '/base/path'),
            array(array('C:/base/path/foo', 'C:/base/path', 'C:/base/path/bar'), 'C:/base/path'),
            array(array('C:\\base\\path\\foo', 'C:\\base\\path', 'C:\\base\\path\\bar'), 'C:/base/path'),
            array(array('C:/base/path//foo', 'C:/base/path', 'C:\\base\\path\\bar'), 'C:/base/path'),
            array(array('phar:///base/path/foo', 'phar:///base/path', 'phar:///base/path/bar'), 'phar:///base/path'),
            array(array('phar://C:/base/path/foo', 'phar://C:/base/path', 'phar://C:/base/path/bar'), 'phar://C:/base/path'),

            // three paths with root
            array(array('/base/path/foo', '/', '/base/path/bar'), '/'),
            array(array('C:/base/path/foo', 'C:/', 'C:/base/path/bar'), 'C:/'),
            array(array('C:\\base\\path\\foo', 'C:\\', 'C:\\base\\path\\bar'), 'C:/'),
            array(array('C:/base/path//foo', 'C:/', 'C:\\base\\path\\bar'), 'C:/'),
            array(array('phar:///base/path/foo', 'phar:///', 'phar:///base/path/bar'), 'phar:///'),
            array(array('phar://C:/base/path/foo', 'phar://C:/', 'phar://C:/base/path/bar'), 'phar://C:/'),

            // three paths, different roots
            array(array('/base/path/foo', 'C:/base/path', '/base/path/bar'), null),
            array(array('/base/path/foo', 'C:\\base\\path', '/base/path/bar'), null),
            array(array('C:/base/path/foo', 'D:/base/path', 'C:/base/path/bar'), null),
            array(array('C:\\base\\path\\foo', 'D:\\base\\path', 'C:\\base\\path\\bar'), null),
            array(array('C:/base/path//foo', 'D:/base/path', 'C:\\base\\path\\bar'), null),
            array(array('phar:///base/path/foo', 'phar://C:/base/path', 'phar:///base/path/bar'), null),
            array(array('phar://C:/base/path/foo', 'phar://D:/base/path', 'phar://C:/base/path/bar'), null),

            // only one path
            array(array('/base/path'), '/base/path'),
            array(array('C:/base/path'), 'C:/base/path'),
            array(array('C:\\base\\path'), 'C:/base/path'),
            array(array('phar:///base/path'), 'phar:///base/path'),
            array(array('phar://C:/base/path'), 'phar://C:/base/path'),
        );
    }

    /**
     * @dataProvider provideGetLongestCommonBasePathTests
     */
    public function testGetLongestCommonBasePath(array $paths, $basePath)
    {
        $this->assertSame($basePath, Path::get_longest_common_base_path($paths));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The paths must be strings. Got: array
     */
    public function testGetLongestCommonBasePathFailsIfInvalidPath()
    {
        Path::get_longest_common_base_path(array( array()));
    }

    public function provideIsBasePathTests()
    {
        return array(
            // same paths
            array('/base/path', '/base/path', true),
            array('C:/base/path', 'C:/base/path', true),
            array('C:\\base\\path', 'C:\\base\\path', true),
            array('C:/base/path', 'C:\\base\\path', true),
            array('phar:///base/path', 'phar:///base/path', true),
            array('phar://C:/base/path', 'phar://C:/base/path', true),

            // trailing slash
            array('/base/path/', '/base/path', true),
            array('C:/base/path/', 'C:/base/path', true),
            array('C:\\base\\path\\', 'C:\\base\\path', true),
            array('C:/base/path/', 'C:\\base\\path', true),
            array('phar:///base/path/', 'phar:///base/path', true),
            array('phar://C:/base/path/', 'phar://C:/base/path', true),

            array('/base/path', '/base/path/', true),
            array('C:/base/path', 'C:/base/path/', true),
            array('C:\\base\\path', 'C:\\base\\path\\', true),
            array('C:/base/path', 'C:\\base\\path\\', true),
            array('phar:///base/path', 'phar:///base/path/', true),
            array('phar://C:/base/path', 'phar://C:/base/path/', true),

            // first in second
            array('/base/path/sub', '/base/path', false),
            array('C:/base/path/sub', 'C:/base/path', false),
            array('C:\\base\\path\\sub', 'C:\\base\\path', false),
            array('C:/base/path/sub', 'C:\\base\\path', false),
            array('phar:///base/path/sub', 'phar:///base/path', false),
            array('phar://C:/base/path/sub', 'phar://C:/base/path', false),

            // second in first
            array('/base/path', '/base/path/sub', true),
            array('C:/base/path', 'C:/base/path/sub', true),
            array('C:\\base\\path', 'C:\\base\\path\\sub', true),
            array('C:/base/path', 'C:\\base\\path\\sub', true),
            array('phar:///base/path', 'phar:///base/path/sub', true),
            array('phar://C:/base/path', 'phar://C:/base/path/sub', true),

            // first is prefix
            array('/base/path/di', '/base/path/dir', false),
            array('C:/base/path/di', 'C:/base/path/dir', false),
            array('C:\\base\\path\\di', 'C:\\base\\path\\dir', false),
            array('C:/base/path/di', 'C:\\base\\path\\dir', false),
            array('phar:///base/path/di', 'phar:///base/path/dir', false),
            array('phar://C:/base/path/di', 'phar://C:/base/path/dir', false),

            // second is prefix
            array('/base/path/dir', '/base/path/di', false),
            array('C:/base/path/dir', 'C:/base/path/di', false),
            array('C:\\base\\path\\dir', 'C:\\base\\path\\di', false),
            array('C:/base/path/dir', 'C:\\base\\path\\di', false),
            array('phar:///base/path/dir', 'phar:///base/path/di', false),
            array('phar://C:/base/path/dir', 'phar://C:/base/path/di', false),

            // root
            array('/', '/second', true),
            array('C:/', 'C:/second', true),
            array('C:', 'C:/second', true),
            array('C:\\', 'C:\\second', true),
            array('C:/', 'C:\\second', true),
            array('phar:///', 'phar:///second', true),
            array('phar://C:/', 'phar://C:/second', true),

            // windows vs unix
            array('/base/path', 'C:/base/path', false),
            array('C:/base/path', '/base/path', false),
            array('/base/path', 'C:\\base\\path', false),
            array('/base/path', 'phar:///base/path', false),
            array('phar:///base/path', 'phar://C:/base/path', false),

            // different partitions
            array('C:/base/path', 'D:/base/path', false),
            array('C:/base/path', 'D:\\base\\path', false),
            array('C:\\base\\path', 'D:\\base\\path', false),
            array('C:/base/path', 'phar://C:/base/path', false),
            array('phar://C:/base/path', 'phar://D:/base/path', false),
        );
    }

    /**
     * @dataProvider provideIsBasePathTests
     */
    public function testIsBasePath($path, $ofPath, $result)
    {
        $this->assertSame($result, Path::is_base_path($path, $ofPath));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The base path must be a string. Got: array
     */
    public function testIsBasePathFailsIfInvalidBasePath()
    {
        Path::is_base_path(array(), '/base/path');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The path must be a string. Got: array
     */
    public function testIsBasePathFailsIfInvalidPath()
    {
        Path::is_base_path('/base/path', array());
    }

    public function provideJoinTests()
    {
        return array(
            array('', '', ''),
            array('/path/to/test', '', '/path/to/test'),
            array('/path/to//test', '', '/path/to/test'),
            array('', '/path/to/test', '/path/to/test'),
            array('', '/path/to//test', '/path/to/test'),

            array('/path/to/test', 'subdir', '/path/to/test/subdir'),
            array('/path/to/test/', 'subdir', '/path/to/test/subdir'),
            array('/path/to/test', '/subdir', '/path/to/test/subdir'),
            array('/path/to/test/', '/subdir', '/path/to/test/subdir'),
            array('/path/to/test', './subdir', '/path/to/test/subdir'),
            array('/path/to/test/', './subdir', '/path/to/test/subdir'),
            array('/path/to/test/', '../parentdir', '/path/to/parentdir'),
            array('/path/to/test', '../parentdir', '/path/to/parentdir'),
            array('path/to/test/', '/subdir', 'path/to/test/subdir'),
            array('path/to/test', '/subdir', 'path/to/test/subdir'),
            array('../path/to/test', '/subdir', '../path/to/test/subdir'),
            array('path', '../../subdir', '../subdir'),
            array('/path', '../../subdir', '/subdir'),
            array('../path', '../../subdir', '../../subdir'),

            array(array('/path/to/test', 'subdir'), '', '/path/to/test/subdir'),
            array(array('/path/to/test', '/subdir'), '', '/path/to/test/subdir'),
            array(array('/path/to/test/', 'subdir'), '', '/path/to/test/subdir'),
            array(array('/path/to/test/', '/subdir'), '', '/path/to/test/subdir'),

            array(array('/path'), '', '/path'),
            array(array('/path', 'to', '/test'), '', '/path/to/test'),
            array(array('/path', '', '/test'), '', '/path/test'),
            array(array('path', 'to', 'test'), '', 'path/to/test'),
            array(array(), '', ''),

            array('base/path', 'to/test', 'base/path/to/test'),

            array('C:\\path\\to\\test', 'subdir', 'C:/path/to/test/subdir'),
            array('C:\\path\\to\\test\\', 'subdir', 'C:/path/to/test/subdir'),
            array('C:\\path\\to\\test', '/subdir', 'C:/path/to/test/subdir'),
            array('C:\\path\\to\\test\\', '/subdir', 'C:/path/to/test/subdir'),

            array('/', 'subdir', '/subdir'),
            array('/', '/subdir', '/subdir'),
            array('C:/', 'subdir', 'C:/subdir'),
            array('C:/', '/subdir', 'C:/subdir'),
            array('C:\\', 'subdir', 'C:/subdir'),
            array('C:\\', '/subdir', 'C:/subdir'),
            array('C:', 'subdir', 'C:/subdir'),
            array('C:', '/subdir', 'C:/subdir'),

            array('phar://', '/path/to/test', 'phar:///path/to/test'),
            array('phar:///', '/path/to/test', 'phar:///path/to/test'),
            array('phar:///path/to/test', 'subdir', 'phar:///path/to/test/subdir'),
            array('phar:///path/to/test', 'subdir/', 'phar:///path/to/test/subdir'),
            array('phar:///path/to/test', '/subdir', 'phar:///path/to/test/subdir'),
            array('phar:///path/to/test/', 'subdir', 'phar:///path/to/test/subdir'),
            array('phar:///path/to/test/', '/subdir', 'phar:///path/to/test/subdir'),

            array('phar://', 'C:/path/to/test', 'phar://C:/path/to/test'),
            array('phar://', 'C:\\path\\to\\test', 'phar://C:/path/to/test'),
            array('phar://C:/path/to/test', 'subdir', 'phar://C:/path/to/test/subdir'),
            array('phar://C:/path/to/test', 'subdir/', 'phar://C:/path/to/test/subdir'),
            array('phar://C:/path/to/test', '/subdir', 'phar://C:/path/to/test/subdir'),
            array('phar://C:/path/to/test/', 'subdir', 'phar://C:/path/to/test/subdir'),
            array('phar://C:/path/to/test/', '/subdir', 'phar://C:/path/to/test/subdir'),
            array('phar://C:', 'path/to/test', 'phar://C:/path/to/test'),
            array('phar://C:', '/path/to/test', 'phar://C:/path/to/test'),
            array('phar://C:/', 'path/to/test', 'phar://C:/path/to/test'),
            array('phar://C:/', '/path/to/test', 'phar://C:/path/to/test'),
        );
    }

    /**
     * @dataProvider provideJoinTests
     */
    public function testJoin($path1, $path2, $result)
    {
        $this->assertSame($result, Path::join($path1, $path2));
    }

    public function testJoinVarArgs()
    {
        $this->assertSame('/path', Path::join('/path'));
        $this->assertSame('/path/to', Path::join('/path', 'to'));
        $this->assertSame('/path/to/test', Path::join('/path', 'to', '/test'));
        $this->assertSame('/path/to/test/subdir', Path::join('/path', 'to', '/test', 'subdir/'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The paths must be strings. Got: array
     */
    public function testJoinFailsIfInvalidPath()
    {
        Path::join('/path', array());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Your environment or operation system isn't supported
     */
    public function testGetHomeDirectoryFailsIfNotSupportedOperationSystem()
    {
        putenv('HOME=');

        Path::get_home_directory();
    }

    public function testGetHomeDirectoryForUnix()
    {
        $this->assertEquals('/home/webmozart', Path::get_home_directory());
    }

    public function testGetHomeDirectoryForWindows()
    {
        putenv('HOME=');
        putenv('HOMEDRIVE=C:');
        putenv('HOMEPATH=/users/webmozart');

        $this->assertEquals('C:/users/webmozart', Path::get_home_directory());
    }

    public function testNormalize()
    {
        $this->assertSame('C:/Foo/Bar/test', Path::normalize('C:\\Foo\\Bar/test'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNormalizeFailsIfNoString()
    {
        Path::normalize(true);
    }
}

# CakePHP Structarmed preset

StructArmed preset for CakePHP 5 application conventions.

Reuses the ideas of StructArmed's built-in `MVC` preset (layer isolation, thin
entry points, naming, safety rules) but maps them to CakePHP's real layout.

## Layers

| Layer | CakePHP namespace | Naming |
|---|---|---|
| `Controller` | `<App>\Controller\FooController` | suffix `Controller` |
| `Component` | `<App>\Controller\Component\FooComponent` | suffix `Component` |
| `Table` | `<App>\Model\Table\FooTable` | suffix `Table` |
| `Entity` | `<App>\Model\Entity\Foo` | — |
| `Behavior` | `<App>\Model\Behavior\FooBehavior` | suffix `Behavior` |
| `View` | `<App>\View\AppView` | — |
| `Cell` | `<App>\View\Cell\FooCell` | suffix `Cell` |
| `Helper` | `<App>\View\Helper\FooHelper` | suffix `Helper` |
| `Command` | `<App>\Command\FooCommand` | suffix `Command` |

`<App>` is the root namespace of your application (or plugin), passed to the
constructor (default `App`).

Templates are not classes — the preset calls `skipPathsForRuleset(['templates/'])`
so `templates/*` is still scanned for PSR checks but excluded from layer rules.

## Install

```bash
composer require --dev crustum/structarmed-cakephp
```

## Usage

```php
// structarmed.php
use Boundwize\StructArmed\Architecture;
use Crustum\StructArmed\Cake\CakeAppPreset;

return Architecture::define()
    ->layer('Source', [])                 // PSR-4 scan paths (needed for the PHPUnit extension)
    ->withPreset(new CakeAppPreset())
    ->ruleset([                           // your own domain layers, if any
        // ...
    ]);
```

Tune it like the built-in presets:

```php
new CakeAppPreset(
    namespace: 'App',           // root namespace (no trailing backslash)
    controllerMaxComplexity: 3, // default: 5
    controllerMaxMethodLength: 15, // default: 20
    controllerMaxDependencies: 4, // default: 5
    viewMaxComplexity: 2,       // default: 3 — View classes should just load helpers
)
```

Skip a rule or a path:

```php
use Crustum\StructArmed\Cake\CakeAppPreset;

return Architecture::define()
    ->withPreset(new CakeAppPreset())
    ->skip(CakeAppPreset::CONTROLLER_MAX_DEPENDENCIES);          // whole rule
    // or: ->skip([CakeAppPreset::ENTITY_MUST... => ['src/Legacy/']]);
```

## Rule keys

All keys are public constants on `CakeAppPreset`, prefixed with `cakephp.`:

- Layer isolation: `TABLE_NOT_DEPEND_CONTROLLER`, `TABLE_NOT_DEPEND_VIEW`,
  `ENTITY_NOT_DEPEND_CONTROLLER`, `ENTITY_NOT_DEPEND_VIEW`,
  `VIEW_NOT_DEPEND_MODEL`, `VIEW_NOT_DEPEND_CONTROLLER`,
  `HELPER_NOT_DEPEND_MODEL`, `HELPER_NOT_DEPEND_CONTROLLER`,
  `CELL_NOT_DEPEND_CONTROLLER`, `COMMAND_NOT_DEPEND_VIEW`
- Naming: `*_NAME_MUST_END_WITH_*`
- Quality: `CONTROLLER_MAX_COMPLEXITY`, `CONTROLLER_MAX_METHOD_LENGTH`,
  `CONTROLLER_MAX_DEPENDENCIES`, `*_NO_SUPERGLOBALS`, `*_MUST_HAVE_RETURN_TYPES`,
  `VIEW_MAX_COMPLEXITY`, `CELL_MAX_COMPLEXITY`, `COMMAND_MAX_COMPLEXITY`
- Safety: `cakephp.safety.<layer>_no_<fn|construct>` (dd, dump, var_dump,
  print_r, var_export, die, exit) across Controller/Table/Entity/View/Helper/Command

## What is intentionally not enforced

- `MustBeFinalRule` — CakePHP controllers/tables are meant to be extended, not final.
- `MustBeInterfaceRule` — no repository interfaces in Cake; Table is the interface.
- Doctrine/PDO bans — Cake uses its own ORM; `DateTime` is fine in Model layer.

## Requirements

* PHP 8.2+

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.

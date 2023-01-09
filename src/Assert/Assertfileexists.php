<?php

namespace Migrator\Assert;

class Assertfileexists extends AssertParent
{

  public function assert(array $args): bool
  {
    if(empty($args['filename'])) {
      throw new \Exception("assertfileexists requires a 'filename' field");
    }
    return file_exists($args['filename']);
  }

}
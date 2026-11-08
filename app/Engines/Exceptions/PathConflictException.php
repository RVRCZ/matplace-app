<?php

namespace App\Engines\Exceptions;

/**
 * The slicer refused a plate because the paths of two of its objects cross ("gcode path conflicts found between …",
 * OrcaSlicer's CLI_GCODE_PATH_CONFLICTS): the supports of one instance reach into another, the tower stands on a part.
 * A plate of copies sliced as instances falls back to the glued mesh on it (PrepareFarmOrder, docs/AL.md §11.10).
 */
class PathConflictException extends SlicerException {}

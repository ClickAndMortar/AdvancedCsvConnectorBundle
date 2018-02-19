#!/bin/bash
# Split large CSV files in chunks

# Exit on errors
set -e

FILE_PATH=${1}
LINES_PER_FILE=${2}
TARGET_FOLDER=${3}
PREFIX_FILES=${4}

# Help menu
print_help() {
cat <<-HELP
This script is used to split large CSV files in chunks.
You need to provide the following arguments:
1) CSV file path.
2) Lines per each new file.
3) Target folder for new files.
4) Prefix for new generated files.
Usage: bash ${0##*/} --file_path=FILE_PATH --lines_per_file=LINES_PER_FILE --target_folder=TARGET_FOLDER --prefix=PREFIX_FILES
Example: bash ${0##*/} --file_path=/tmp/frames_csv.csv --lines_per_file=500 --target_folder=/tmp --prefix=products_part_
HELP
exit 0
}

# Parse Command Line Arguments
while [ $# -gt 0 ]; do
        case "$1" in
                --file_path=*)
      FILE_PATH="${1#*=}"
      ;;
    --lines_per_file=*)
      LINES_PER_FILE="${1#*=}"
      ;;
    --target_folder=*)
      TARGET_FOLDER="${1#*=}"
      ;;
    --prefix=*)
      PREFIX_FILES="${1#*=}"
      ;;
    --help) print_help;;
    *)
      printf "Invalid argument, run --help for valid arguments.\n";
      exit 1
  esac
  shift
done

if [ -e $FILE_PATH ] ; then
    # Create files
    tail -n +2 $FILE_PATH | split -l $LINES_PER_FILE --additional-suffix=.csv - $TARGET_FOLDER/$PREFIX_FILES

    # Add header for all files
    for CURRENT_FILE in $TARGET_FOLDER/$PREFIX_FILES*
    do
        head -n 1 $FILE_PATH > $TARGET_FOLDER/tmp_file
        cat $CURRENT_FILE >> $TARGET_FOLDER/tmp_file
        mv -f $TARGET_FOLDER/tmp_file $CURRENT_FILE
    done

    # Delete original file
    rm -f $FILE_PATH
    printf "$FILE_PATH file splitted !\n"
fi
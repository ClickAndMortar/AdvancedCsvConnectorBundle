#!/bin/bash
# Split large CSV files in chunks

# Exit on errors
set -e

FILE_PATH=${1}
LINES_PER_FILE=${2}
TARGET_FOLDER=${3}
PREFIX_FILES=${4}
SEPARATOR=";"
ENCLOSURE='"'

# Help menu
print_help() {
cat <<-HELP
This script is used to split large CSV files in chunks.
You need to provide the following arguments:
1) CSV file path.
2) Lines per each new file.
3) Target folder for new files.
4) Prefix for new generated files.
5) Separator (optional, default: ;).
6) Enclosure (optional, default: ").
Usage: bash ${0##*/} --file_path=FILE_PATH --lines_per_file=LINES_PER_FILE --target_folder=TARGET_FOLDER --prefix=PREFIX_FILES [--separator=SEPARATOR] [--enclosure=ENCLOSURE]
Example: bash ${0##*/} --file_path=/tmp/frames_csv.csv --lines_per_file=500 --target_folder=/tmp --prefix=products_part_ --separator=;
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
    --separator=*)
      SEPARATOR="${1#*=}"
      ;;
    --enclosure=*)
      ENCLOSURE="${1#*=}"
      ;;
    --help) print_help;;
    *)
      printf "Invalid argument, run --help for valid arguments.\n";
      exit 1
  esac
  shift
done

if [ -e $FILE_PATH ] ; then
    # Split CSV respecting multiline fields: a line break only ends a record
    # when it is not inside an enclosed field
    awk -v lines_per_file="$LINES_PER_FILE" \
        -v target_folder="$TARGET_FOLDER" \
        -v prefix="$PREFIX_FILES" \
        -v sep="$SEPARATOR" \
        -v enclosure="$ENCLOSURE" \
    'BEGIN {
        file_num = 0
        line_count = 0
        current_record = ""
        inside_enclosure = 0
        # Generate suffix like split command (aa, ab, ac...)
        split("a b c d e f g h i j k l m n o p q r s t u v w x y z", letters, " ")
    }

    function get_suffix(num) {
        first = int(num / 26)
        second = num % 26
        return letters[first + 1] letters[second + 1]
    }

    function write_record(record) {
        if (line_count % lines_per_file == 0) {
            if (file_num > 0) {
                close(current_file)
            }
            current_file = target_folder "/" prefix get_suffix(file_num) ".csv"
            file_num++
        }
        print record > current_file
        line_count++
    }

    # Return 1 if the record is still inside an enclosed field at the end of
    # given line, 0 otherwise. An enclosure only opens a field at the start of
    # a field (start of record or right after a separator), and a doubled
    # enclosure inside an enclosed field is an escaped enclosure character.
    function ends_inside_enclosure(line, inside, previous,    pos, before) {
        if (enclosure == "") {
            return 0
        }
        while ((pos = index(line, enclosure)) > 0) {
            before = (pos == 1) ? previous : substr(line, pos - 1, 1)
            if (inside) {
                if (substr(line, pos + 1, 1) == enclosure) {
                    line = substr(line, pos + 2)
                } else {
                    inside = 0
                    line = substr(line, pos + 1)
                }
            } else {
                if (before == "" || before == sep) {
                    inside = 1
                }
                line = substr(line, pos + 1)
            }
            previous = enclosure
        }

        return inside
    }

    NR == 1 {
        # Store header, do not write yet (will be added later)
        header = $0
        next
    }

    {
        # Skip blank lines between records
        if (current_record == "" && ($0 == "" || $0 == "\r")) {
            next
        }

        if (current_record == "") {
            current_record = $0
            inside_enclosure = ends_inside_enclosure($0, 0, "")
        } else {
            current_record = current_record "\n" $0
            inside_enclosure = ends_inside_enclosure($0, 1, "\n")
        }

        # Check if we have a complete record
        if (!inside_enclosure) {
            write_record(current_record)
            current_record = ""
        }
    }

    END {
        # Write any remaining record
        if (current_record != "") {
            write_record(current_record)
        }
        if (file_num > 0) {
            close(current_file)
        }
    }' "$FILE_PATH"

    # Add header for all files
    for CURRENT_FILE in $TARGET_FOLDER/$PREFIX_FILES*
    do
        head -n 1 "$FILE_PATH" > "$TARGET_FOLDER/tmp_file"
        cat "$CURRENT_FILE" >> "$TARGET_FOLDER/tmp_file"
        mv -f "$TARGET_FOLDER/tmp_file" "$CURRENT_FILE"
    done

    # Delete original file
    rm -f "$FILE_PATH"
    printf "$FILE_PATH file splitted !\n"
fi
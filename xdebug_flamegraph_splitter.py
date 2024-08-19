#!/usr/bin/env python3

"""
XDebug Flamegraph Splitter

This script processes large gzipped XDebug trace files and generates multiple flamegraphs.
It's designed to handle trace files that are too big for the FlameGraph tool to process directly.

Features:
1. Unzips the input gzipped XDebug trace file.
2. Splits the large trace file into smaller parts.
3. Generates SVG flamegraphs for each part using the FlameGraph tool.
4. Opens the resulting SVG files in Chromium for easy viewing.

Requirements:
- FlameGraph tool (https://github.com/brendangregg/FlameGraph.git) must be cloned in the same directory.
- Chromium browser for viewing the SVGs.

Usage:
python3 xdebug_flamegraph_splitter.py <gzipped_trace_file> [--split-size SIZE]

Arguments:
  gzipped_trace_file    The gzipped XDebug trace file to process
  --split-size SIZE     Size of split files (default: 25M)

Example:
python3 xdebug_flamegraph_splitter.py my_trace.xt.gz --split-size 50M

Note: This script creates a temporary working directory and suggests cleanup commands after execution.
"""


import os
import gzip
import shutil
import subprocess
import argparse
from pathlib import Path
from time import sleep

FLAMEGRAPH_REPO = "https://github.com/brendangregg/FlameGraph.git"

def verify_flamegraph_script():
    flamegraph_script = Path('FlameGraph/flamegraph.pl')
    if not flamegraph_script.exists():
        print(f"Error: FlameGraph script not found at {flamegraph_script}")
        print(f"Please clone the FlameGraph repository using:")
        print(f"git clone {FLAMEGRAPH_REPO}")
        exit(1)
    return flamegraph_script

def suggest_cleanup(work_dir):
    if work_dir.exists():
        print(f"Warning: The directory {work_dir} already exists.")
        print(f"To clean it up, you can run the following command:")
        print(f"rm -rf {work_dir}")
        print("Please review and run this command manually if you want to clean up.")
        print("Then re-run this script.")
        exit(1)

def create_flamegraphs(gzipped_trace_file, split_size):
    flamegraph_script = verify_flamegraph_script()

    # Create a new directory to work in
    base_name = Path(gzipped_trace_file).stem
    work_dir = Path(f"{base_name}_flamegraphs")
    suggest_cleanup(work_dir)
    work_dir.mkdir()

    # Create an ungzipped copy of the trace in that dir
    ungzipped_file = work_dir / f"{base_name}.xt"
    with gzip.open(gzipped_trace_file, 'rb') as f_in:
        with open(ungzipped_file, 'wb') as f_out:
            shutil.copyfileobj(f_in, f_out)

    # Use split to create smaller files
    subprocess.run(['split', '-b', split_size, ungzipped_file, work_dir / 'trace_part_'])

    # Loop over the split files and make svg files for each of them
    for part_file in work_dir.glob('trace_part_*'):
        svg_file = work_dir / f"{part_file.name}.svg"
        with open(part_file, 'r') as infile, open(svg_file, 'w') as outfile:
            subprocess.run([flamegraph_script, '--width=1600'], stdin=infile, stdout=outfile, check=True)

    # Use chromium to open the svgs in a loop
    svg_files = sorted(work_dir.glob('*.svg'))
    if svg_files:
        for svg_file in svg_files:
            subprocess.Popen(['chromium', svg_file])
            sleep(0.2)
    else:
        print("No SVG files were generated. There might be an issue with the trace file or its format.")

    # Suggest cleanup
    print("\nTo clean up temporary files, you can run the following command:")
    print(f"rm -rf {work_dir}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Create flamegraphs from a gzipped trace file.')
    parser.add_argument('gzipped_trace_file', help='The gzipped trace file to process')
    parser.add_argument('--split-size', default='25M', help='Size of split files (default: 25M)')
    
    args = parser.parse_args()
    
    create_flamegraphs(args.gzipped_trace_file, args.split_size)

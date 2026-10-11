#!/usr/bin/env ruby
# frozen_string_literal: true

require 'psych'

# Verify the parser rejects Issue #151's malformed YAML heredoc regression.
def verify_heredoc_regression!
  prefix = ['name: Regression fixture', 'jobs:', '  audit:', '    steps:', '      - run: |', "          node <<'NODE'"]
  malformed = (prefix + ['const crypto = require("node:crypto")', 'NODE']).join("\n") + "\n"
  corrected = (prefix + ['          const crypto = require("node:crypto")', '          NODE']).join("\n") + "\n"

  begin
    Psych.parse(malformed)
    abort '[workflow-yaml] ERROR: malformed unindented heredoc was accepted'
  rescue Psych::SyntaxError
    # Expected: GitHub Actions rejects this file before creating any jobs.
  end
  begin
    Psych.parse(corrected)
  rescue Psych::SyntaxError => error
    abort "[workflow-yaml] ERROR: corrected heredoc was rejected: #{error.message}"
  end
  puts '[workflow-yaml] heredoc parser regression passed'
end

verify_heredoc_regression! if ARGV.include?('--self-test')

repository_root = File.expand_path('..', __dir__)
workflows = Dir.glob(File.join(repository_root, '.github', 'workflows', '*.{yml,yaml}')).sort
abort '[workflow-yaml] ERROR: no GitHub Actions workflow files found' if workflows.empty?

failures = 0
workflows.each do |file|
  relative = file.delete_prefix(repository_root + File::SEPARATOR)
  begin
    document = Psych.parse_file(file)
    raise Psych::SyntaxError.new(file, 1, 1, 0, 'empty workflow', '') unless document
    puts "[workflow-yaml] valid #{relative}"
  rescue Psych::SyntaxError => error
    failures += 1
    warn "[workflow-yaml] INVALID #{relative}:#{error.line}:#{error.column}: #{error.problem}"
  end
end
abort "[workflow-yaml] ERROR: #{failures} invalid workflow(s)" if failures.positive?
puts "[workflow-yaml] PASS: #{workflows.length} workflow(s) parsed"

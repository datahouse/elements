all: elements.pdf todo.png

clean:
	rm -f todo.png elements.pdf elements.aux elements.listing \
		elements.log elements.out
	rm -rf _minted-elements

elements.pdf: elements.tex
	pdflatex -shell-escape elements.tex
	pdflatex -shell-escape elements.tex

todo.png: todo.dot
	# requires graphviz
	neato -Tpng todo.dot > todo.png

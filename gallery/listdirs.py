import numpy as np
import os

a = os.listdir('photos/')
a = np.array([str(a)])
np.savetxt('dirs.txt', a, fmt="%s")